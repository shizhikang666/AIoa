<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="项目名称" name="projectName">
						<a-input v-model:value="searchFormState.projectName" placeholder="请输入项目名称" />
					</a-form-item>
				</a-col>

				<a-col :span="6">
					<a-form-item label="项目编号" name="projectCode">
						<a-input v-model:value="searchFormState.projectCode" placeholder="请输入项目编号" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="发货类型" name="shipmentScope">
						<a-select v-model:value="searchFormState.shipmentScope" :options="shipmentScopeOptions" />
					</a-form-item>
				</a-col>

				<a-col :span="6" v-show="advanced">
					<a-form-item label="付款状态" name="playState">
						<a-select
							mode="multiple"
							v-model:value="searchFormState.playState"
							placeholder="请选择付款状态"
							:options="playStateOptions"
						/>
					</a-form-item>
				</a-col>

				<a-col :span="6" v-show="advanced">
					<a-form-item label="项目显示状态" name="visibility">
						<a-select
							v-model:value="searchFormState.visibility"
							placeholder="请选择项目显示状态"
							:options="visibilityOptions"
						/>
					</a-form-item>
				</a-col>

				<a-col :span="6" v-show="advanced">
					<a-form-item label="类别直采" name="projectCategory">
						<a-select
							v-model:value="searchFormState.projectCategory"
							placeholder="请选择类别直采||默认"
							:options="projectCategoryOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="项目负责人" name="user">
						<a-input v-model:value="searchFormState.user" placeholder="请输入项目负责人" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.createTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
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
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					<a @click="toggleAdvanced" style="margin-left: 8px">
						{{ advanced ? '收起' : '展开' }}
						<component :is="advanced ? 'up-outlined' : 'down-outlined'" />
					</a>
				</a-col>
			</a-row>
		</a-form>

		<br />
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex == 'projectName'">
					<a-typography-link @click="invoiceFormRef.onOpen(record)">{{ record.projectName }} </a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'projectState'">
					<a-tag :color="$TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE_COLOR', record.projectState)">
						{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE', record.projectState) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'playState'">
					<a-tag :color="$TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE_COLOR', record.playState)">
						{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_PLAY_STATE', record.playState) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'visibility'">
					<a-switch
						@click="changeVisibility(record)"
						checked-children="公开"
						un-checked-children="私有"
						:checked="record.visibility === 'PUBLIC'"
					/>
				</template>
				<template v-if="column.dataIndex === 'projectCategory'">
					{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'PROJECT_CATEGORY', record.projectCategory) }}
				</template>
				<template v-if="column.dataIndex === 'shipmentType'">
					<a-space :size="4" wrap>
						<a-tag v-if="record.hasPendingNormalShipment" color="blue">正常发货</a-tag>
						<a-tag v-if="record.hasPendingReissue" color="orange">
							补发待发<span v-if="record.pendingReissueOrderCount">（{{ record.pendingReissueOrderCount }}单）</span>
						</a-tag>
					</a-space>
				</template>
				<template v-if="column.dataIndex === 'pendingQuantity'">
					<a-space direction="vertical" :size="0">
						<span v-if="record.hasPendingNormalShipment">正常：{{ record.pendingNormalQuantity }}</span>
						<span v-if="record.hasPendingReissue" class="reissue-quantity">补发：{{ record.pendingReissueQuantity }}</span>
					</a-space>
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a @click="openAddProjectDelivery(record)">
						{{ record.hasPendingReissue && !record.hasPendingNormalShipment ? '处理补发' : '处理发货' }}
					</a>
				</template>
			</template>
		</s-table>
	</a-card>
	<Detail ref="detailRef" @successful="tableRef.refresh()"></Detail>
	<start-project-delivery-flow-form
		ref="startProjectDeliveryFlowForm"
		@successful="tableRef.refresh()"
	></start-project-delivery-flow-form>
	<invoice-form ref="invoiceFormRef"></invoice-form>

	<!--<start-project-return-flow-form ref="startProjectReturnFlowFormRef"></start-project-return-flow-form>-->
</template>
<script setup name="saleproject">
	import { App } from 'ant-design-vue'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import Detail from './detail.vue'
	import StartProjectDeliveryFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectDeliveryFlowForm.vue'
	import { useTemplateRef } from 'vue'
	import { Decimal } from 'decimal.js'
	import InvoiceForm from '@/views/biz/saleproject/form/invoiceForm.vue'
	import { useOrg } from '@/composables/useOrg'
	const { treeData, loadingTreeData, findTopCompanyByOrgId } = useOrg()
	const { message, modal, notification } = App.useApp()

	const searchFormState = ref({ shipmentScope: 'ALL' })
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const detailRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const startProjectDeliveryFlowForm = ref()

	const invoiceFormRef = useTemplateRef('invoiceFormRef')
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}

	const columns = [
		{
			title: '项目名称',
			dataIndex: 'projectName'
		},
		{
			title: '发货类型',
			dataIndex: 'shipmentType',
			width: 150
		},
		{
			title: '待发数量',
			dataIndex: 'pendingQuantity',
			width: 110
		},
		{
			title: '项目状态',
			dataIndex: 'projectState'
		},
		{
			title: '付款状态',
			dataIndex: 'playState'
		},
		{
			title: '类别',
			dataIndex: 'projectCategory'
		},
		{
			title: '项目所属公司',
			dataIndex: 'companyName'
		},
		{
			title: '项目负责人',
			dataIndex: 'headName'
		},
		// {
		// 	title: '创建时间',
		// 	dataIndex: 'createTime'
		// },
		{
			title: '最后更新时间',
			dataIndex: 'updateTime'
		},
		{
			title: '成交时间',
			dataIndex: 'completionDate'
		},
		{
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 200
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
	const loadData = async (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}

		if (searchFormParam.playState) {
			searchFormParam.playState = searchFormParam.playState.join(',')
		}

		await loadingTreeData()

		return bizSaleProjectApi
			.bizSaleProjectPage(
				Object.assign(parameter, searchFormParam, {
					shipmentScope: searchFormParam.shipmentScope || 'ALL',
					sortField: 'updateTime'
				})
			)
			.then((data) => {
				data.records = data.records.map((v) => {
					v.company = findTopCompanyByOrgId(treeData.value, v.org)
					v.companyName = v.company ? v.company.name : ''

					v.totalPrice = v.totalPrice ? v.totalPrice : 0
					v.rebateAmount = v.rebateAmount ? v.rebateAmount : 0
					let result = new Decimal(v.totalPrice).sub(new Decimal(v.rebateAmount))
					let dealPrice = result.toString()
					let obj = { ...v, dealPrice }
					return obj
				})
				return data
			})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		searchFormState.value.shipmentScope = 'ALL'
		tableRef.value.refresh(true)
	}

	// 批量删除

	const playStateOptions = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_PLAY_STATE')
	const visibilityOptions = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_VISIBILITY')
	const projectCategoryOptions = tool.dictListByPath('SALE_PROJECT', 'PROJECT_CATEGORY')
	const shipmentScopeOptions = [
		{ label: '全部待发', value: 'ALL' },
		{ label: '正常发货', value: 'NORMAL' },
		{ label: '补发', value: 'REISSUE' }
	]

	const openAddProjectDelivery = (record) => {
		startProjectDeliveryFlowForm.value.onOpen(record)
	}

	const changeVisibility = async (record) => {
		await bizSaleProjectApi.bizSaleProjectVisibilityEdit({
			projectId: record.id,
			visibilityState: record.visibility === 'PUBLIC' ? 'PRIVATE' : 'PUBLIC'
		})
		record.visibility = record.visibility === 'PUBLIC' ? 'PRIVATE' : 'PUBLIC'
	}
</script>

<style scoped>
	.reissue-quantity {
		color: #d46b08;
	}
</style>
