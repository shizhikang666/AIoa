<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="仓库编号" name="warehousesId">
						<a-input v-model:value="searchFormState.warehousesId" placeholder="请输入仓库编号" />
					</a-form-item>
				</a-col>

				<a-col :span="6" v-show="advanced">
					<a-form-item label="出库类型" name="category">
						<a-input v-model:value="searchFormState.category" placeholder="请输入出库类型" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="流程分类" name="processCategory">
						<a-input v-model:value="searchFormState.processCategory" placeholder="请输入流程分类" />
					</a-form-item>
				</a-col>

				<a-col :span="6">
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
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'category'">
					<a-typography-text :type="record.category === 'IN' ? 'success' : 'danger'">
						<template>
							{{ $TOOL.dictTypeDataByPath('WAREHOUSES', 'DeliveryRecordCategory', record.category) }}
						</template>
					</a-typography-text>
				</template>

				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link @click="processDetailsRef.onOpen({ instanceId: record.processId })">
						{{ record.processId }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'processCategory'">
					<template v-if="record.processCategory === 'Process_sys'">系统盘点</template>
					<template v-else>
						{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_category', record.processCategory) }}
					</template>
				</template>
			</template>
		</s-table>
	</a-card>
	<processDetails ref="processDetailsRef"></processDetails>
</template>

<script setup name="inventoryInfo">
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'

	const props = defineProps({
		productId: {
			type: String,
			default: ''
		}
	})
	import { cloneDeep } from 'lodash-es'

	import deliveryRecordApi from '@/api/biz/deliveryRecordApi'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const processDetailsRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '仓库名称',
			dataIndex: 'warehousesName'
		},
		{
			title: '流程编号',
			dataIndex: 'processId',
			ellipsis: true
		},
		{
			title: '产品名称',
			dataIndex: 'productName'
		},
		{
			title: '出库类型',
			dataIndex: 'category'
		},
		{
			title: '数量',
			dataIndex: 'amount'
		},

		{
			title: '流程分类',
			dataIndex: 'processCategory'
		},
		{
			title: '经办人',
			dataIndex: 'operatorName'
		},
		{
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '出库时间',
			dataIndex: 'deliveryTime',
			ellipsis: true
		}
	]

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

		return deliveryRecordApi
			.deliveryRecordPage(Object.assign(parameter, { ...searchFormParam, ...props }))
			.then((data) => {
				return data
			})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
</script>
