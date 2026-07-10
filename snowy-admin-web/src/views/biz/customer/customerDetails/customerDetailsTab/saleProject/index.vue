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
					<a-form-item label="项目状态" name="projectState">
						<a-select
							v-model:value="searchFormState.projectState"
							placeholder="请选择项目状态"
							:options="projectStateOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="付款状态" name="playState">
						<a-select
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
				<!--				<a-col :span="6" v-show="advanced">-->
				<!--					<a-form-item label="累计金额" name="totalPrice">-->
				<!--						<a-input v-model:value="searchFormState.totalPrice" placeholder="请输入累计金额" />-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->
				<!--				<a-col :span="6" v-show="advanced">-->
				<!--					<a-form-item label="累计收款金额" name="amountCollected">-->
				<!--						<a-input v-model:value="searchFormState.amountCollected" placeholder="请输入累计收款金额" />-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->
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
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			:alert="options.alert.show"
			:scroll="{ x: 1300 }"
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
								customer: customerId,
								projectCategory: 'DIRECT',
								customerName: customerName,
								customerFileId: customerFileId,
								customerDownloadPath: customerDownloadPath,
								area: address,
								projectName: ''
							})
						"
						v-if="hasPerm('bizSaleProjectAdd')"
					>
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>
					<xn-batch-delete
						confirmTitle="作废此信息？"
						buttonName="批量作废"
						v-if="hasPerm('bizSaleProjectBatchRepeal')"
						:selectedRowKeys="selectedRowKeys"
						@batchDelete="deleteBatchBizSaleProject"
					/>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex == 'projectName' && hasPerm('bizSaleProjectEdit')">
					<a-typography-link
						@click="detailRef.onOpen(record)"
						v-if="record.projectState === 'DISCARD'"
						type="danger"
						delete
						>{{ record.projectName }}
					</a-typography-link>
					<a-typography-link @click="detailRef.onOpen(record)" v-else>{{ record.projectName }} </a-typography-link>
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
						:disabled="record.visibility === 'PUBLIC'"
						:checked="record.visibility === 'PUBLIC'"
					/>
				</template>
				<template v-if="column.dataIndex === 'projectCategory'">
					{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'PROJECT_CATEGORY', record.projectCategory) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('bizSaleProjectEdit')">编辑</a>
						<a-divider type="vertical" v-if="hasPerm(['bizSaleProjectEdit', 'bizSaleProjectDelete'], 'and')" />
						<!--						<a-popconfirm title="确定要删除吗？" @confirm="deleteBizSaleProject(record)">-->
						<!--							<a-button :disabled="record.projectState!='FOLLOW'" type="link" danger size="small" v-if="hasPerm('bizSaleProjectDelete')">删除 </a-button>-->
						<!--						</a-popconfirm>-->

						<a-popconfirm title="确定要作废吗？" @confirm="repealBizSaleProject(record)">
							<template #description>
								<a-textarea placeholder="作废原因" v-model:value="repealContent" />
							</template>
							<a-button
								:disabled="!['FOLLOW', 'WAIT_DELIVER'].includes(record.projectState)"
								type="link"
								danger
								size="small"
								v-if="hasPerm('bizSaleProjectRepeal')"
								>作废
							</a-button>
						</a-popconfirm>
					</a-space>
					<a-divider type="vertical" v-if="hasPerm(['bizSaleProjectDetail'])" />
					<a-dropdown v-if="hasPerm(['bizSaleProjectDetail', 'bizSaleProjectStartProcess', ''])">
						<a class="ant-dropdown-link">
							{{ $t('common.more') }}
							<DownOutlined />
						</a>
						<template #overlay>
							<a-menu>
								<a-menu-item v-if="hasPerm('bizSaleProjectDetail')">
									<a-anchor-link @click="detailRef.onOpen(record)">{{ $t('common.detailButton') }} </a-anchor-link>
								</a-menu-item>
								<a-menu-item v-if="hasPerm('bizSaleProjectStartProcess') && record.projectState === 'FOLLOW'">
									<a-anchor-link @click="startProcess(record)">{{ $t('common.processButton') }} </a-anchor-link>
								</a-menu-item>
								<a-menu-item>
									<a-anchor-link @click="openAddPlayForm(record)" type="danger">添加收款 </a-anchor-link>
								</a-menu-item>
								<a-menu-item
									v-if="record.projectState === 'PARTIALLY_SHIPPED' || record.projectState === 'WAIT_DELIVER'"
								>
									<a-anchor-link @click="openAddProjectDelivery(record)" type="danger">添加发货单 </a-anchor-link>
								</a-menu-item>
							</a-menu>
						</template>
					</a-dropdown>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<Detail ref="detailRef" @successful="tableRef.refresh()"></Detail>
	<start-flow-form @successful="tableRef.refresh()" ref="startFlowFormRef"></start-flow-form>
	<start-play-flow-form @successful="tableRef.refresh()" ref="startPlayFlowFormRef"></start-play-flow-form>
	<start-project-delivery-flow-form
		ref="startProjectDeliveryFlowForm"
		@successful="tableRef.refresh()"
	></start-project-delivery-flow-form>

	<a-modal v-model:open="openPublicModel" :confirm-loading="visibilityFormLoading" @ok="submitVisibility">
		<a-form ref="publicFormRef" :model="publicForm" layout="vertical">
			<a-form-item required label="标底类型：" name="specimenCategory">
				<a-select v-model:value="publicForm.specimenCategory" placeholder="请选择标底类型" :options="specimenOptions" />
			</a-form-item>
			<a-form-item name="specimenName" v-if="publicForm.specimenCategory === 'GrabTheBid'" required label="品牌名称">
				<a-input placeholder="品牌名称" v-model:value="publicForm.specimenName"></a-input>
			</a-form-item>
		</a-form>
	</a-modal>
</template>
<script setup name="saleproject">
	import { App } from 'ant-design-vue'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from '@/views/biz/saleproject/form.vue'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import Detail from '@/views/biz/saleproject/detail.vue'
	import StartFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectInitFlowForm.vue'
	import StartPlayFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectPlayFlowForm.vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import StartProjectDeliveryFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectDeliveryFlowForm.vue'
	import { useLoading } from '@/composables/useLoading'
	import { useTemplateRef } from 'vue'

	const { message, modal, notification } = App.useApp()
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const detailRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const startProjectDeliveryFlowForm = ref()
	const startFlowFormRef = ref()
	const startPlayFlowFormRef = ref()
	const props = defineProps({
		customerId: {
			type: String,
			required: true
		},
		customerName: {
			type: String,
			required: true
		},
		address: {
			type: String
		},
		customerFileId: {
			type: String
		},
		customerDownloadPath: {
			type: String
		}
	})

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
			title: '项目状态',
			dataIndex: 'projectState'
		},
		{
			title: '付款状态',
			dataIndex: 'playState'
		},
		{
			title: '项目显示状态',
			dataIndex: 'visibility'
		},
		{
			title: '订单初始金额',
			dataIndex: 'initPrice'
		},
		{
			title: '累计金额',
			dataIndex: 'totalPrice'
		},
		{
			title: '累计收款金额',
			dataIndex: 'amountCollected'
		},
		{
			title: '类别',
			dataIndex: 'projectCategory'
		},
		{
			title: '项目负责人',
			dataIndex: 'headName'
		},
		{
			title: '创建时间',
			dataIndex: 'createTime'
		}
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['bizSaleProjectEdit', 'bizSaleProjectDelete', 'bizSaleProjectDetail', 'bizSaleProjectRepeal'])) {
		columns.push({
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 200
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
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		return bizSaleProjectApi
			.bizSaleProjectPage(
				Object.assign(parameter, searchFormParam, {
					sortOrder: 'descend',
					sortField: 'createTime',
					customer: props.customerId
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
	const deleteBizSaleProject = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizSaleProjectApi.bizSaleProjectDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}

	const repealContent = ref('')

	const repealBizSaleProject = (record) => {
		let params = [
			{
				repealContent: repealContent.value,
				id: record.id
			}
		]
		bizSaleProjectApi.repealBizSaleProject(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	const repealBatchBizSaleProject = (params) => {
		bizSaleProjectApi.repealBizSaleProject(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}

	// 批量删除
	const deleteBatchBizSaleProject = (params) => {
		bizSaleProjectApi.bizSaleProjectDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
	const projectStateOptions = tool.dictListByPath(['SALE_PROJECT', 'SALE_PROJECT_STATE'])
	const playStateOptions = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_PLAY_STATE')
	const visibilityOptions = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_VISIBILITY')
	const projectCategoryOptions = tool.dictListByPath('SALE_PROJECT', 'PROJECT_CATEGORY')

	const startProcess = (record) => {
		startFlowFormRef.value.onOpen(record)
	}

	const openAddPlayForm = (record) => {
		startPlayFlowFormRef.value.onOpen(record)
	}

	const openAddProjectDelivery = (record) => {
		startProjectDeliveryFlowForm.value.onOpen(record)
	}

	const publicFormRef = useTemplateRef('publicFormRef')
	const specimenOptions = ref([])
	specimenOptions.value = tool.dictListByPath('SALE_PROJECT', 'specimenCategory')
	const activeRecord = ref({})
	const openPublicModel = ref(false)
	const publicForm = ref({
		specimenCategory: '',
		specimenName: ''
	})
	const {
		load: submitVisibility,
		loading: visibilityFormLoading,
		error
	} = useLoading(async () => {
		const record = activeRecord.value
		try {
			await publicFormRef.value.validateFields()
		} catch (e) {
			return
		}

		await bizSaleProjectApi.bizSaleProjectVisibilityEdit({
			projectId: record.id,
			visibilityState: 'PUBLIC',
			specimenCategory: publicForm.value.specimenCategory,
			specimenName: publicForm.value.specimenName
		})
		activeRecord.value.visibility = 'PUBLIC'
		openPublicModel.value = false
	})

	const changeVisibility = async (record) => {
		openPublicModel.value = true
		activeRecord.value = record
	}
</script>
