<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="产品名称" name="productName">
						<a-input v-model:value="searchFormState.productName" placeholder="请输入产品名称" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="产品分类" name="productCategory">
						<a-select
							v-model:value="searchFormState.productCategory"
							placeholder="请选择产品分类"
							:options="productCategoryOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="类别" name="category">
						<a-select
							v-model:value="searchFormState.category"
							placeholder="请选择类别 单品 套件"
							:options="categoryOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced" v-if="hasPerm('editReconciliation')">
					<a-form-item label="是否启用对账" name="reconciliationType">
						<a-select
							v-model:value="searchFormState.reconciliationType"
							placeholder="是否启用对账"
							:options="statusOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced" v-if="hasPerm('editReconciliation')">
					<a-form-item label="对账金额" name="reconciliationAmount">
						<a-input-number v-model:value="searchFormState.reconciliationAmount" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="过滤范围" name="onlyCurrentCompany">
						<a-checkbox v-model:checked="searchFormState.onlyCurrentCompany">仅查看本公司产品</a-checkbox>
					</a-form-item>
				</a-col>

				<a-col :span="12" v-show="advanced">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.createTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6" class="query-btn">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					<a @click="toggleAdvanced" style="margin-left: 8px">
						{{ advanced ? '收起' : '展开' }}
						<component :is="advanced ? 'up-outlined' : 'down-outlined'" />
					</a>
				</a-col>
			</a-row>
		</a-form>
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			:alert="options.alert.show"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
			:row-selection="options.rowSelection"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button
						type="primary"
						@click="
							formRef.onOpen({
								category: 'SINGLE_PRODUCT'
							})
						"
						v-if="hasPerm('bizProductAdd')"
					>
						<template #icon>
							<plus-outlined />
						</template>
						新增单品产品
					</a-button>

					<a-button
						type="primary"
						@click="
							formRef.onOpen({
								category: 'KIT_PRODUCT'
							})
						"
						v-if="hasPerm('bizKitProductAdd')"
					>
						<template #icon>
							<plus-outlined />
						</template>
						新增套件产品
					</a-button>

					<a-button @click="openEditReconciliationForm" v-if="hasPerm('editReconciliation')">修改对账金额 </a-button>

					<!--					<xn-batch-delete-->
					<!--						v-if="hasPerm('bizProductBatchDelete')"-->
					<!--						:selectedRowKeys="selectedRowKeys"-->
					<!--						@batchDelete="deleteBatchBizProduct"-->
					<!--					/>-->
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'productName'">
					<a-typography-link @click="productDetail.onOpen(record)">{{ record.productName }} </a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'warehouseInventory'">
					<a-space v-if="record.warehouseInventory?.length" :size="[0, 4]" wrap>
						<a-tag v-for="item in record.warehouseInventory" :key="item.warehouseId" color="blue">
							{{ item.warehouseName || item.warehouseCode || '未命名仓库' }}：
							<span :style="{ color: Number(item.currentCount || 0) < 0 ? '#ff4d4f' : '' }">
								{{ item.currentCount ?? 0 }}
							</span>
						</a-tag>
					</a-space>
					<a-typography-text v-else type="secondary">未录入仓库</a-typography-text>
				</template>
				<template v-if="column.dataIndex === 'totalInventory'">
					<span :style="{ color: Number(record.totalInventory || 0) < 0 ? '#ff4d4f' : '' }">
						{{ record.totalInventory ?? 0 }}
					</span>
				</template>
				<template v-if="column.dataIndex === 'productCategory'">
					{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
				</template>
				<template v-if="column.dataIndex === 'specs'">
					{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SPECS', record.specs) }}
				</template>
				<template v-if="column.dataIndex === 'Status'">
					<a-switch :loading="loading" :checked="record.status === 'ENABLE'" @change="editStatus(record)" />
				</template>
				<template v-if="column.dataIndex === 'category'">
					<a-tag :color="$TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE_COLOR', record.category)">
						{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE', record.category) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('bizProductEdit')">编辑</a>
						<a-divider type="vertical" v-if="hasPerm(['bizProductEdit', 'bizProductDelete'], 'and')" />
						<a-popconfirm title="确定要删除吗？" @confirm="deleteBizProduct(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('bizProductDelete')">删除</a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<Detail ref="productDetail"></Detail>

	<a-modal v-model:open="openReconciliationModel" :confirm-loading="visibilityFormLoading" @ok="submitEditForm">
		<a-form ref="reconciliationFormRef" :model="reconciliationForm" layout="vertical">
			<a-form-item required label="对账开关：" name="reconciliationType">
				<a-select
					v-model:value="reconciliationForm.reconciliationType"
					placeholder="对账类型"
					:options="reconciliationOptions"
				/>
			</a-form-item>
			<a-form-item name="reconciliationAmount" label="对账金额">
				<xn-currency-input v-model="reconciliationForm.reconciliationAmount"></xn-currency-input>
			</a-form-item>
		</a-form>
	</a-modal>
</template>

<script setup name="bizproduct">
	import { getCurrentInstance, useTemplateRef } from 'vue'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizProductApi from '@/api/biz/bizProductApi'
	import Detail from './details/details.vue'
	import { message } from 'ant-design-vue'
	import { useLoading } from '@/composables/useLoading'
	import { useOrg } from '@/composables/useOrg'

	// 修改状态

	const searchFormState = ref({
		onlyCurrentCompany: true
	})
	const searchFormRef = ref()
	const tableRef = ref()

	const formRef = ref()

	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)

	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}

	const productDetail = ref()

	const context = getCurrentInstance()
	const { loadingTreeData, getCurrentUserTopOrgId } = useOrg()
	const currentUserTopOrgId = ref(null)
	const initCurrentUserTopOrgId = async () => {
		await loadingTreeData()
		currentUserTopOrgId.value = getCurrentUserTopOrgId()
		return currentUserTopOrgId.value
	}
	const currentUserTopOrgIdReady = initCurrentUserTopOrgId()

	const loading = ref(false)
	const editStatus = (record) => {
		loading.value = true
		if (record.status === 'ENABLE') {
			bizProductApi
				.bizProductEditStatus({
					id: record.id,
					status: 'DISABLE'
				})
				.then(() => {
					tableRef.value.refresh()
				})
				.finally(() => {
					loading.value = false
				})
		} else {
			bizProductApi
				.bizProductEditStatus({
					id: record.id,
					status: 'ENABLE'
				})
				.then(() => {
					tableRef.value.refresh()
				})
				.finally(() => {
					loading.value = false
				})
		}
	}

	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName'
		},
		{
			title: '状态',
			dataIndex: 'Status',
			width: '80px'
		},
		{
			title: '产品分类',
			dataIndex: 'productCategory'
		},
		{
			title: '安全库存',
			dataIndex: 'safetyStock'
		},
		{
			title: '库存仓库',
			dataIndex: 'warehouseInventory',
			width: 260
		},
		{
			title: '总库存',
			dataIndex: 'totalInventory',
			width: 90
		},
		{
			title: '采购价格',
			dataIndex: 'purchasePrice'
		},
		{
			title: '销售价格',
			dataIndex: 'salePrice'
		},
		{
			title: '最低售价',
			dataIndex: 'minPrice'
		},
		{
			title: '类别',
			dataIndex: 'category'
		},
		{
			title: '规格',
			dataIndex: 'specs'
		},
		{
			title: '创建时间',
			dataIndex: 'createTime'
		},
		{
			title: '创建用户',
			dataIndex: 'createUserName'
		}
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['bizProductEdit', 'bizProductDelete'])) {
		columns.push({
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 150
		})
	}

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
	const loadData = async (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		await currentUserTopOrgIdReady
		const requestParam = Object.assign(parameter, searchFormParam, { showDisabledProducts: true })
		if (requestParam.onlyCurrentCompany && currentUserTopOrgId.value) {
			requestParam.orgId = currentUserTopOrgId.value
		}
		delete requestParam.onlyCurrentCompany
		return bizProductApi
			.bizProductPage(requestParam)
			.then((data) => {
				return data
			})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		searchFormState.value.onlyCurrentCompany = true
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteBizProduct = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizProductApi.bizProductDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchBizProduct = (params) => {
		bizProductApi.bizProductDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
	const productCategoryOptions = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_TYPE'])
	const categoryOptions = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_SYS_TYPE'])
	const statusOptions = tool.dictList('COMMON_STATUS')
	//启用
	const reconciliationOptions = ref(tool.dictListByPath('COMMON_STATUS'))

	const reconciliationForm = ref({})
	const openReconciliationModel = ref(false)
	const reconciliationFormRef = useTemplateRef('reconciliationFormRef')
	const openEditReconciliationForm = () => {
		if (!selectedRowKeys.value.length) {
			message.warn('请选择！')
			return
		}
		openReconciliationModel.value = true
	}

	const { loading: visibilityFormLoading, load: submitEditForm } = useLoading(async () => {
		try {
			await reconciliationFormRef.value.validate()
		} catch (e) {
			console.log(e)
		}
		const formDataParam = cloneDeep(reconciliationForm.value)
		await bizProductApi.bizProductReconciliationEdit({
			...formDataParam,
			ids: selectedRowKeys.value
		})
		openReconciliationModel.value = false
		tableRef.value.refresh()
	})
</script>

<style lang="less" scoped>
.query-btn {
	margin-bottom: 24px;
}
</style>
