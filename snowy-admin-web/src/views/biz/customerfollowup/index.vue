<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<!--				<a-col :span="6">-->
				<!--					<a-form-item label="CUSTOMER_ID" name="customerId">-->
				<!--						<a-input v-model:value="searchFormState.customerId" placeholder="请输入CUSTOMER_ID" />-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->
				<a-col :span="6">
					<a-form-item label="跟进时间" name="followUpTime">
						<a-range-picker v-model:value="searchFormState.followUpTime" value-format="YYYY-MM-DD HH:mm:ss" show-time />
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
					<!--					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('customerFollowUpAdd')">-->
					<!--						<template #icon><plus-outlined /></template>-->
					<!--						新增-->
					<!--					</a-button>-->
					<!--					<xn-batch-delete-->
					<!--						v-if="hasPerm('customerFollowUpBatchDelete')"-->
					<!--						:selectedRowKeys="selectedRowKeys"-->
					<!--						@batchDelete="deleteBatchCustomerFollowUp"-->
					<!--					/>-->
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'customerName'">
					<a-typography-link
						@click="
							customerDetailRef.onOpen({
								id: record.customerId
							})
						"
						>{{ record.customerName }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('customerFollowUpEdit')">编辑</a>
						<a-divider type="vertical" v-if="hasPerm(['customerFollowUpEdit', 'customerFollowUpDelete'], 'and')" />
						<a-popconfirm title="确定要删除吗？" @confirm="deleteCustomerFollowUp(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('customerFollowUpDelete')">删除 </a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<Detail ref="customerDetailRef"></Detail>
</template>

<script setup name="customerfollowup">
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'

	import Detail from '@/views/biz/customer/customerDetails/customerDetails.vue'
	import customerFollowUpApi from '@/api/biz/customerFollowUpApi'
	import { useTemplateRef } from 'vue'

	const customerDetailRef = useTemplateRef('customerDetailRef')
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const columns = [
		{
			title: '客户名称',
			dataIndex: 'customerName'
		},
		{
			title: '跟进时间',
			dataIndex: 'followUpTime'
		},
		{
			title: '内容',
			dataIndex: 'content',
			ellipsis: true
		}
	]
	// 操作栏通过权限判断是否显示
	// if (hasPerm(['customerFollowUpEdit', 'customerFollowUpDelete'])) {
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
		// followUpTime范围查询条件重载
		if (searchFormParam.followUpTime) {
			searchFormParam.startFollowUpTime = searchFormParam.followUpTime[0]
			searchFormParam.endFollowUpTime = searchFormParam.followUpTime[1]
			delete searchFormParam.followUpTime
		}
		return customerFollowUpApi.customerFollowUpPage(Object.assign(parameter, searchFormParam)).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteCustomerFollowUp = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		customerFollowUpApi.customerFollowUpDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchCustomerFollowUp = (params) => {
		customerFollowUpApi.customerFollowUpDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
</script>
