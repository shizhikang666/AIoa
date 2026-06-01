<template>
	<a-card ref="cardRef" :bordered="false">
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
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('bizHistoryExcelAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>
					<xn-batch-delete
						v-if="hasPerm('bizHistoryExcelBatchDelete')"
						:selectedRowKeys="selectedRowKeys"
						@batchDelete="deleteBatchBizHistoryExcel"
					/>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex == 'name'">
					<a-typography-link @click="bizHistoryExcelDetailsRef.onOpen(record)">
						{{ record.name }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="bizHistoryExcelDetailsRef.onOpen(record)" v-if="hasPerm('bizHistoryExcelEdit')">编辑</a>
						<a-divider type="vertical" v-if="hasPerm(['bizHistoryExcelEdit', 'bizHistoryExcelDelete'], 'and')" />
						<a-popconfirm title="确定要删除吗？" @confirm="deleteBizHistoryExcel(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('bizHistoryExcelDelete')">删除 </a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<bizHistoryExcelDetails
		@successful="tableRef.refresh()"
		:width="cardWidth"
		ref="bizHistoryExcelDetailsRef"
	></bizHistoryExcelDetails>
</template>

<script setup name="bizhistoryexcel">
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizHistoryExcelApi from '@/api/biz/bizHistoryExcelApi'
	import bizHistoryExcelDetails from './details/details.vue'
	import { useTemplateRef } from 'vue'

	//const cardRef = ref(null)
	const cardWidth = ref(document.body.clientWidth + 'px')
	// onMounted(() => {
	// 	if (cardRef.value.$el) {
	// 		const cardElement = cardRef.value.$el
	// 		cardWidth.value = cardElement.offsetWidth + 'px'
	// 	}
	// })
	const bizHistoryExcelDetailsRef = useTemplateRef('bizHistoryExcelDetailsRef')
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const columns = [
		{
			title: '表名称',
			dataIndex: 'name'
		},
		{
			title: '创建时间',
			dataIndex: 'createTime'
		},
		{
			title: '创建用户',
			dataIndex: 'createUser'
		},
		{
			title: '修改时间',
			dataIndex: 'updateTime'
		},
		{
			title: '修改用户',
			dataIndex: 'updateUser'
		}
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['bizHistoryExcelEdit', 'bizHistoryExcelDelete'])) {
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
	const loadData = (parameter) => {
		return bizHistoryExcelApi.bizHistoryExcelPage(parameter).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteBizHistoryExcel = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizHistoryExcelApi.bizHistoryExcelDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchBizHistoryExcel = (params) => {
		bizHistoryExcelApi.bizHistoryExcelDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
</script>
