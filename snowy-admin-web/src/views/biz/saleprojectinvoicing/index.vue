<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="开票公司" name="companyName">
						<a-input v-model:value="searchFormState.companyName" placeholder="请输入开票公司" />
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
					<a-form-item label="项目编号" name="projectId">
						<a-input v-model:value="searchFormState.projectId" placeholder="请输入项目编号" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="开票状态" name="invoicingState">
						<a-select
							v-model:value="searchFormState.invoicingState"
							placeholder="请选择开票状态"
							:options="invoicingStateOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="开票类型" name="invoicingCategory">
						<a-select
							v-model:value="searchFormState.invoicingCategory"
							placeholder="请选择开票类型"
							:options="invoicingCategoryOptions"
						/>
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
					<a @click="toggleAdvanced" style="margin-left: 8px">
						{{ advanced ? '收起' : '展开' }}
						<component :is="advanced ? 'up-outlined' : 'down-outlined'" />
					</a>
				</a-col>
			</a-row>
		</a-form>
		<s-table
			:scroll="{ x: 2000 }"
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('bizSaleProjectInvoicingAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>
					<xn-batch-delete
						v-if="hasPerm('bizSaleProjectInvoicingBatchDelete')"
						:selectedRowKeys="selectedRowKeys"
						@batchDelete="deleteBatchBizSaleProjectInvoicing"
					/>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'projectId'">
					<a-typography-link @click="openSaleProjectDetail(record.projectId)">
						{{ record.projectId }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'invoicingState'">
					<a-tag :color="record.invoicingState !== 'INVOICING_STATE_COMPLETE' ? 'red' : 'green'">
						{{ $TOOL.dictTypeData('INVOICING_STATE', record.invoicingState) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'invoicingCategory'">
					{{ $TOOL.dictTypeData('InvoicingCategory', record.invoicingCategory) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-typography-link
							:disabled="record.invoicingState === 'INVOICING_STATE_COMPLETE'"
							@click="markState(record)"
							v-if="hasPerm('bizSaleProjectInvoicingEdit')"
						>
							标记为已开
						</a-typography-link>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<SaleProjectDetail ref="SaleProjectDetailRef"></SaleProjectDetail>
</template>

<script setup name="saleprojectinvoicing">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizSaleProjectInvoicingApi from '@/api/biz/bizSaleProjectInvoicingApi'
	import SaleProjectDetail from '@/views/biz/saleproject/detail.vue'
	import { useTemplateRef } from 'vue'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const markState = async (record) => {
		await bizSaleProjectInvoicingApi.bizSaleProjectInvoicingComplete({
			id: record.id
		})
		tableRef.value.refresh()
	}

	const SaleProjectDetailRef = useTemplateRef('SaleProjectDetailRef')
	const openSaleProjectDetail = (id) => {
		SaleProjectDetailRef.value.onOpen({ id: id })
	}

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '项目编号',
			dataIndex: 'projectId',
			width: 300
		},
		{
			title: '申请人',
			dataIndex: 'createUserName',
			width: 100
		},

		{
			title: '开票金额',
			dataIndex: 'amount',
			width: 200
		},
		{
			title: '开票状态',
			dataIndex: 'invoicingState',
			width: 100
		},
		{
			title: '开票类型',
			dataIndex: 'invoicingCategory',
			width: 100
		},

		{
			title: '开票公司',
			dataIndex: 'companyName',
			width: 300
		},
		{
			title: '客户公司',
			dataIndex: 'customerCompany',
			width: 300
		},
		{
			title: '单位全称',
			dataIndex: 'unit',
			width: 300
		},

		{
			title: '纳税人号',
			dataIndex: 'taxpayer',
			width: 300
		},
		{
			title: '对公账户',
			dataIndex: 'corporateAccount',
			width: 300
		},
		{
			title: '开户银行',
			dataIndex: 'bankName',
			width: 300
		},
		{
			title: '单位地址',
			dataIndex: 'unitAddress',
			width: 300
		},
		{
			title: '单位电话',
			dataIndex: 'unitPhone',
			width: 300
		},
		{
			title: '联系电话',
			dataIndex: 'phone',
			width: 300
		},
		{
			title: '发票地址',
			dataIndex: 'harvestAddress',
			width: 300
		},
		{
			title: '备注',
			dataIndex: 'remark',
			width: 300
		},
		{
			title: '创建时间',
			dataIndex: 'createTime',
			width: 300
		}
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['bizSaleProjectInvoicingEdit'])) {
		columns.push({
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 150,
			key: 'operation',
			fixed: 'right'
		})
	}
	const selectedRowKeys = ref([])
	// 列表选择配置
	const options = {
		// columns数字类型字段加入 needTotal: true 可以勾选自动算账
	}
	const loadData = (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		return bizSaleProjectInvoicingApi
			.bizSaleProjectInvoicingPage(
				Object.assign(parameter, searchFormParam, {
					sortField: 'invoicingState'
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
	const deleteBizSaleProjectInvoicing = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizSaleProjectInvoicingApi.bizSaleProjectInvoicingDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchBizSaleProjectInvoicing = (params) => {
		bizSaleProjectInvoicingApi.bizSaleProjectInvoicingDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
	const invoicingStateOptions = tool.dictList('INVOICING_STATE')
	const invoicingCategoryOptions = tool.dictList('InvoicingCategory')
</script>
