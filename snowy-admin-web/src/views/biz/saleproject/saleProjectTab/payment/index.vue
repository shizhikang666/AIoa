<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker v-model:value="searchFormState.createTime" value-format="YYYY-MM-DD HH:mm:ss" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="创建用户" name="createUser">
						<a-input v-model:value="searchFormState.createUser" placeholder="请输入创建用户" />
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
			:alert="options.alert.show"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
			:row-selection="options.rowSelection"
		>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex == 'processId'">
					<a-typography-link @click="detailRef.onOpen({ instanceId: record.processId })"
						>{{ record.processId }}
					</a-typography-link>
				</template>
			</template>
		</s-table>
	</a-card>
	<processDetails ref="detailRef"></processDetails>
</template>

<script setup name="saleprojectpayment">
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'

	import { cloneDeep } from 'lodash-es'

	const { projectId } = defineProps({
		projectId: String
	})

	import bizPaymentRecordApi from '@/api/biz/bizPaymentRecordApi'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const detailRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const columns = [
		// {
		// 	title: '付款人',
		// 	dataIndex: 'payer'
		// },
		//

		{
			title: '收款账户',
			dataIndex: 'accountName'
		},

		{
			title: '流程实例编号',
			dataIndex: 'processId'
		},
		{
			title: '收款金额',
			dataIndex: 'amount'
		},

		{
			title: '创建用户',
			dataIndex: 'createUserName'
		},
		{
			title: '付款时间',
			dataIndex: 'payerTime'
		},

		{
			title: '创建时间',
			dataIndex: 'createTime'
		},
		{
			title: '备注',
			dataIndex: 'remark'
		}
	]
	// 操作栏通过权限判断是否显示

	const selectedRowKeys = ref([])
	// 列表选择配置
	const options = {
		// columns数字类型字段加入 needTotal: true 可以勾选自动算账
		alert: {
			show: false,
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
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		return bizPaymentRecordApi
			.bizPaymentRecordPage(
				Object.assign(parameter, searchFormParam, {
					objectId: projectId,
					settlementCategory: 'PROJECT_PLAY'
				})
			)
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
