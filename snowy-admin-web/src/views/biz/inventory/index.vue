<template>
	<a-card v-if="currenWareHouse" :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="当前仓库" name="currenWareHouse">
						<a-select
							:fieldNames="{
								label: 'name',
								value: 'id'
							}"
							v-model:value="currenWareHouse"
							:options="currentWareHouseList"
						></a-select>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="产品名称" name="productName">
						<a-input v-model:value="searchFormState.productName" placeholder="请输入产品名称" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
				</a-col>
			</a-row>
		</a-form>
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="handleAdd">录入库存信息</a-button>
					<a-button @click="exportExcelRef.onOpen({ id: currenWareHouse, currentWareHouseList: currentWareHouseList })"
						>导出库存信息</a-button
					>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'productName'">
					<a-typography-link @click="productDetailRef.onOpen({ id: record.productId })"
						>{{ record.productName }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'warehouseName'">
					<a-space direction="vertical" :size="0">
						<span>{{ record.warehouseName || '--' }}</span>
						<a-typography-text v-if="record.warehouseCode" type="secondary">
							{{ record.warehouseCode }}
						</a-typography-text>
					</a-space>
				</template>
				<template v-if="column.dataIndex === 'currentCount'">
					<a-space>
						<span
							:style="{
								color: (record.currentCount ? record.currentCount : 0) < record.safetyStock ? token.colorError : ''
							}"
						>
							{{ record.currentCount ? record.currentCount : 0 }}
						</span>
						<template v-if="(record.currentCount ? record.currentCount : 0) < record.safetyStock">
							<a-tooltip :title="`库存预警数量${record.safetyStock}`" :color="token.colorWarning">
								<ExclamationCircleFilled :style="{ color: token.colorWarning }" />
							</a-tooltip>
						</template>
					</a-space>
				</template>
				<template v-if="column.dataIndex === 'updateTime'">
					{{ record.updateTime ? record.updateTime : '--' }}
				</template>
				<template v-if="column.dataIndex === 'productCategory'">
					{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
				</template>
				<template v-if="column.dataIndex === 'category'">
					<a-tag :color="$TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE_COLOR', record.category)">
						{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE', record.category) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'purchasePrice'">
					{{ record.purchasePrice }}
				</template>

				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('inventoryEdit')">盘点库存</a>
						<!--						<a-divider type="vertical" v-if="hasPerm(['inventoryEdit', 'inventoryDelete'], 'and')" />-->
						<!--						<a-popconfirm title="确定要删除吗？" @confirm="deleteInventory(record)">-->
						<!--							<a-button type="link" danger size="small" v-if="hasPerm('inventoryDelete')">删除</a-button>-->
						<!--						</a-popconfirm>-->
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<a-card :loading="loading" v-else :bordered="false">
		<a-empty>
			<template #description>
				<span>
					未创建仓库
					<a @click="addWareHouse.onOpen()">请先创建仓库</a>
				</span>
			</template>
			<a-button type="primary" @click="addWareHouse.onOpen()">创建</a-button>
		</a-empty>
	</a-card>
	<AddWareHouse @successful="loadWareHouse" ref="addWareHouse"></AddWareHouse>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<productDetail ref="productDetailRef"></productDetail>
	<exportExcel ref="exportExcelRef" />
</template>

<script setup name="inventory">
	import SelectProductModal from '@/views/biz/bizproduct/modal/selectProductModal/index.vue'
	import { cloneDeep } from 'lodash-es'
	import { watch, nextTick, ref, createVNode } from 'vue'
	import Form from './form.vue'
	import AddWareHouse from '@/views/biz/warehouses/form.vue'
	import inventoryApi from '@/api/biz/inventoryApi'
	import warehousesApi from '@/api/biz/warehousesApi'
	import InventoryApi from '@/api/biz/inventoryApi'
	import { App, theme } from 'ant-design-vue'
	const { modal } = App.useApp()
	import productDetail from '@/views/biz/bizproduct/details/details.vue'
	import exportExcel from './exportExcel/index.vue'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }

	const productDetailRef = ref()
	const exportExcelRef = ref()
	const { useToken } = theme
	const { token } = useToken()

	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName'
		},
		{
			title: '所属仓库',
			dataIndex: 'warehouseName'
		},
		{
			title: '产品分类',
			dataIndex: 'productCategory'
		},

		{
			title: '产品类型',
			dataIndex: 'category'
		},
		{
			title: '库存',
			dataIndex: 'currentCount'
		},
		{
			title: '最近更新时间',
			dataIndex: 'updateTime'
		},

		{
			title: '采购价',
			dataIndex: 'purchasePrice'
		},
		{
			title: '操作',
			dataIndex: 'action'
		}
	]
	const addWareHouse = ref()
	//当前仓库
	const currentWareHouseList = ref([])
	const loading = ref(false)
	const error = ref(false)
	const currenWareHouse = ref()

	const handleAdd = () => {
		const modelValue = ref([])
		let content = createVNode(SelectProductModal, {
			// ignoreIdList: formData.value.productList.map((v) => v.productId),
			disableSearchFromKey: {
				// category:true,
			},
			defaultSearchFrom: {
				category: 'SINGLE_PRODUCT'
			},
			modelValue: modelValue,
			'onUpdate:modelValue': (value) => (modelValue.value = value)
		})
		const onOk = async () => {
			const result = modelValue.value.map((item, index) => {
				// return {
				// 	productName: item.productName,
				// 	productCategory: item.productCategory,
				// 	productId: item.id,
				// 	number: 1,
				// 	unitPrice: item.salePrice,
				// 	discountRate: 0,
				// 	price: item.salePrice,
				// 	remark: ''
				// }
				return item.id
			})

			await InventoryApi.inventoryAdd({
				productIds: result,
				warehousesId: currenWareHouse.value
			})
			tableRef.value.refresh()
			// let warProduct = await warpProduct(result, 'productId')
			// formData.value.productList.push(...warProduct)
		}

		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}
	const loadWareHouse = async () => {
		loading.value = true
		error.value = false
		try {
			const res = await warehousesApi.warehousesList()
			currentWareHouseList.value = res
			if (res.length > 0) {
				currenWareHouse.value = res[0].id
			}
			await loadData()
		} catch (error) {
			error.value = true
		} finally {
			loading.value = false
		}
	}
	// 操作栏通过权限判断是否显示
	// if (hasPerm(['inventoryEdit', 'inventoryDelete'])) {
	// 	columns.push({
	// 		title: '操作',
	// 		dataIndex: 'action',
	// 		align: 'center',
	// 		width: 150
	// 	})
	// }
	const selectedRowKeys = ref([])
	// 列表选择配置
	const options = {
		// columns数字类型字段加入 needTotal: true 可以勾选自动算账
		alert: {
			show: true,
			clear: () => {
				selectedRowKeys.value = ref([])
			}
		},
		rowSelection: {
			onChange: (selectedRowKey, selectedRows) => {
				selectedRowKeys.value = selectedRowKey
			}
		}
	}
	const loadData = (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)

		console.log(
			Object.assign(parameter, searchFormParam, {
				warehousesId: currenWareHouse.value
			})
		)
		return inventoryApi
			.inventoryPage(
				Object.assign(parameter, searchFormParam, {
					warehousesId: currenWareHouse.value
				})
			)
			.then((data) => {
				return data
			})
	}
	watch(currenWareHouse, async (value, oldValue, onCleanup) => {
		await nextTick()
		tableRef.value.refresh()
	})
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	loadWareHouse()
</script>
