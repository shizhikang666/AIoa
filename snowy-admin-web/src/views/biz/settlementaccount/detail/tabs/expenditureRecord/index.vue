<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<!--				<a-col :span="6">-->
				<!--					<a-form-item label="流水编号" name="serialId">-->
				<!--						<a-input v-model:value="searchFormState.serialId" placeholder="请输入流水编号" />-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->
				<!--				<a-col :span="6" v-show="advanced">-->
				<!--					<a-form-item label="流程实例编号" name="processId">-->
				<!--						<a-input v-model:value="searchFormState.processId" placeholder="请输入流程实例编号" />-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->
				<a-col :span="6">
					<a-form-item label="金额" name="amount">
						<a-input placeholder="请输入金额" v-model:value="searchFormState.amount"></a-input>
					</a-form-item>
				</a-col>

				<a-col :span="6" v-show="advanced">
					<a-form-item label="结算分类" name="settlementCategory">
						<a-select
							v-model:value="searchFormState.settlementCategory"
							placeholder="请选择结算分类"
							:options="settlementCategoryOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="付款时间" name="payerTime">
						<a-range-picker v-model:value="searchFormState.payerTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker v-model:value="searchFormState.createTime" show-time />
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
			<template #operator class="table-operator"></template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link @click="processDetailsRef.onOpen({ instanceId: record.processId })">
						{{ record.processId }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'settlementCategory'">
					{{
						$TOOL.dictTypeDataByPath(
							'SETTLEMENT_ACCOUNT',
							'SETTLEMENT_CATEGORY',
							'PAY_CATEGORY',
							record.settlementCategory
						)
					}}
				</template>

				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="editTargetFormRef.onOpen(record)">修正结算账户</a>
						<a @click="formRef.onOpen(record)">修正</a>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<processDetails ref="processDetailsRef" />
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<EditTargetForm ref="editTargetFormRef" @successful="tableRef.refresh()"></EditTargetForm>
</template>

<script setup name="bizexpenditurerecord">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import bizExpenditureRecordApi from '@/api/biz/bizExpenditureRecordApi'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import { useTemplateRef } from 'vue'
	import Form from './form.vue'
	import EditTargetForm from './editTargetForm.vue'

	const editTargetFormRef = useTemplateRef('editTargetFormRef')
	const formRef = useTemplateRef('formRef')

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }

	const processDetailsRef = useTemplateRef('processDetailsRef')
	const { accountId } = defineProps({
		accountId: {
			required: true,
			type: String
		}
	})

	// 查询区域显示更多控制
	const advanced = ref(true)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '流程实例编号',
			dataIndex: 'processId'
		},
		{
			title: '结算分类',
			dataIndex: 'settlementCategory'
		},
		{
			title: '收款人',
			dataIndex: 'payer'
		},
		{
			title: '开户行',
			dataIndex: 'bankName'
		},

		{
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '付款时间',
			dataIndex: 'payerTime'
		},
		{
			title: '支出金额',
			dataIndex: 'amount'
		},
		// {
		// 	title: '创建时间',
		// 	dataIndex: 'createTime'
		// },
		{
			title: '操作',
			dataIndex: 'action',

			align: 'center'
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
		// payerTime范围查询条件重载
		if (searchFormParam.payerTime) {
			searchFormParam.startPayerTime = searchFormParam.payerTime[0]
			searchFormParam.endPayerTime = searchFormParam.payerTime[1]
			delete searchFormParam.payerTime
		}
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		return bizExpenditureRecordApi
			.bizExpenditureRecordPage(
				Object.assign(parameter, searchFormParam, {
					targetId: accountId,
					sortField: 'payerTime'
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

	const settlementCategoryOptions = tool.dictListByPath('SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY')
</script>
