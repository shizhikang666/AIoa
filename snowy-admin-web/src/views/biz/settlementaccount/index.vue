<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="账户名称" name="accountName">
						<a-input v-model:value="searchFormState.accountName" placeholder="请输入账户名称" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="所属组织：" name="orgId">
						<a-tree-select
							v-model:value="searchFormState.orgId"
							class="xn-wd"
							:dropdown-style="{ maxHeight: '400px', overflow: 'auto' }"
							placeholder="请选择组织"
							allow-clear
							:tree-data="treeData"
							:field-names="{
								children: 'children',
								label: 'name',
								value: 'id'
							}"
							selectable="false"
							tree-line
						></a-tree-select>
					</a-form-item>
				</a-col>

				<a-col :span="6">
					<a-form-item label="账户编号" name="accountNumber">
						<a-input v-model:value="searchFormState.accountNumber" placeholder="请输入账户编号" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="是否启用" name="accountStatus">
						<a-select
							v-model:value="searchFormState.accountStatus"
							placeholder="请选择是否启用"
							:options="accountStatusOptions"
						/>
					</a-form-item>
				</a-col>

				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
				</a-col>
			</a-row>
			<br />
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
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('settlementAccountAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>

					<a-button @click="transferAccountsFormRef.onOpen()">
						<template #icon>
							<RetweetOutlined />
						</template>
						转账
					</a-button>

					<!--					<xn-batch-delete-->
					<!--						v-if="hasPerm('settlementAccountBatchDelete')"-->
					<!--						:selectedRowKeys="selectedRowKeys"-->
					<!--						@batchDelete="deleteBatchSettlementAccount"-->
					<!--					/>-->
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'accountName'">
					<a-typography-link @click="openDetail(record.id)">{{ record.accountName }}</a-typography-link>
				</template>

				<template v-if="column.dataIndex === 'accountStatus'">
					<a-switch
						:loading="loading"
						:checked="record.accountStatus === 'ENABLE'"
						@change="editStatus($event, record)"
					/>
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<span>
							<a @click="expensesFormRef.onOpen({ targetId: record.id })" v-if="hasPerm('settlementAccountEdit')"
								>支出</a
							>
							/
							<a @click="incomeFormRef.onOpen({ targetId: record.id })" v-if="hasPerm('settlementAccountEdit')">收入</a>
						</span>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('settlementAccountEdit')">编辑</a>
						<!--						<a-divider type="vertical" v-if="hasPerm(['settlementAccountEdit', 'settlementAccountDelete'], 'and')" />-->
						<!--						<a-popconfirm title="确定要删除吗？" @confirm="deleteSettlementAccount(record)">-->
						<!--							<a-button type="link" danger size="small" v-if="hasPerm('settlementAccountDelete')">删除 </a-button>-->
						<!--						</a-popconfirm>-->
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<settlementAccountDetail ref="detailRef"></settlementAccountDetail>
	<expensesForm @successful="tableRef.refresh()" ref="expensesFormRef"></expensesForm>
	<incomeForm @successful="tableRef.refresh()" ref="incomeFormRef"></incomeForm>
	<transferAccountsForm ref="transferAccountsFormRef" @successful="tableRef.refresh()"></transferAccountsForm>
</template>

<script setup name="settlementaccount">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import settlementAccountApi from '@/api/biz/settlementAccountApi'
	import { useTemplateRef } from 'vue'
	import settlementAccountDetail from './detail/index.vue'
	import expensesForm from './expensesForm.vue'
	import incomeForm from './incomeForm.vue'
	import transferAccountsForm from './transferAccountsForm.vue'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()

	const transferAccountsFormRef = useTemplateRef('transferAccountsFormRef')

	const expensesFormRef = useTemplateRef('expensesFormRef')
	const incomeFormRef = useTemplateRef('incomeFormRef')
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const columns = [
		{
			title: '账户名称',
			dataIndex: 'accountName'
		},
		{
			title: '账户编号',
			dataIndex: 'accountNumber'
		},
		{
			title: '初始资金',
			dataIndex: 'initialAmount'
		},
		{
			title: '当前金额',
			dataIndex: 'currentAmount'
		},
		{
			title: '是否启用',
			dataIndex: 'accountStatus'
		},
		{
			title: '所属组织机构',
			dataIndex: 'orgName'
		}
	]

	const detailRef = useTemplateRef('detailRef')
	const openDetail = (id) => {
		detailRef.value.onOpen({ id: id })
	}

	const loading = ref(false)
	//操作栏通过权限判断是否显示
	//'settlementAccountDelete'
	if (hasPerm(['settlementAccountEdit'])) {
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
		const searchFormParam = cloneDeep(searchFormState.value)
		return settlementAccountApi
			.settlementAccountPage(
				Object.assign(parameter, searchFormParam, {
					sortField: 'sortCode',
					sortOrder: 'ASCEND'
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
	// 删除
	const deleteSettlementAccount = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		settlementAccountApi.settlementAccountDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchSettlementAccount = (params) => {
		settlementAccountApi.settlementAccountDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
	const accountStatusOptions = tool.dictList('COMMON_STATUS')

	//切换账号状态
	const editStatus = async (change, record) => {
		loading.value = true

		let formData = Object.assign({}, record)

		tool.dictList('COMMON_STATUS')
		if (change) {
			formData.accountStatus = accountStatusOptions[0].value
		} else {
			formData.accountStatus = accountStatusOptions[1].value
		}

		try {
			await settlementAccountApi.settlementAccountEditStatus(formData)
			record.accountStatus = formData.accountStatus
		} finally {
			loading.value = false
		}
	}
</script>
