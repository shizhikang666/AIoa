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
					<a-form-item label="项目状态" name="projectState">
						<a-select
							mode="multiple"
							v-model:value="searchFormState.projectState"
							placeholder="请选择项目状态"
							:options="projectStateOptions"
						/>
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
					<a-form-item label="有无回扣" name="kickback">
						<a-select
							allow-clear
							v-model:value="searchFormState.kickback"
							placeholder="是否有回扣"
							:options="[
								{
									label: '有回扣',
									value: true
								},
								{
									label: '无回扣',
									value: false
								}
							]"
						/>
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
					<a-form-item label="成交时间" name="completionTime">
						<a-range-picker
							value-format="YYYY-MM-DD HH:mm:ss"
							v-model:value="searchFormState.completionTime"
							show-time
						/>
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
			:alert="options.alert.show"
			bordered
			:scroll="{ x: 1600 }"
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
			:row-selection="options.rowSelection"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('bizSaleProjectAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>
					<a-button @click="openExport" type="primary ">
						<template #icon>
							<DownloadOutlined />
						</template>

						导出数据
					</a-button>
					<a-button v-if="hasPerm('bizSaleProjectHistoryAdd')" @click="historyFormRef.onOpen()">
						录入历史订单
					</a-button>
					<xn-batch-delete
						confirmTitle="作废此信息？"
						buttonName="批量作废"
						v-if="hasPerm('bizSaleProjectBatchRepeal')"
						:selectedRowKeys="selectedRowKeys"
						@batchDelete="deleteBatchBizSaleProject"
					/>

					<!--					<a-checkbox v-model:checked="showDiscard"> 显示已作废项目</a-checkbox>-->
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex == 'projectName'">
					<a-badge dot :count="record.processIdList.length">
						<a-typography-link
							@click="detailRef.onOpen(record)"
							:type="record.projectState === 'DISCARD' ? 'danger' : 'default'"
							:delete="record.projectState === 'DISCARD'"
						>
							{{ record.projectName }}
							<a-typography-text v-if="record.returnOrders.length" type="danger"> (有退货!!! ) </a-typography-text>
						</a-typography-link>
					</a-badge>
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
				<template v-if="column.dataIndex === 'paymentProgress'">
					<a-row justify="center">
						<a-progress :percent="record.paymentProgress" />
					</a-row>
				</template>
				<template v-if="column.dataIndex === 'projectCategory'">
					{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'PROJECT_CATEGORY', record.projectCategory) }}
				</template>
				<template v-if="column.dataIndex === 'customerSourceType'">
					{{ $TOOL.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_SOURCE', record.customerSourceType) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-popconfirm title="确定恢复为跟进状态吗？" @confirm="restoreProject(record)">
							<a-button type="link" size="small" v-if="hasPerm('bizSaleProjectCancel')">恢复跟进</a-button>
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
								<a-menu-item
									:disabled="record.disabledPayment"
									@click="openAddPlayForm(record)"
									v-if="
										record.projectState !== 'FOLLOW' &&
										record.projectState !== 'DISCARD' &&
										record.projectState !== 'PENDING_APPROVAL'
									"
								>
									<a-anchor-link type="link" size="small"> 添加收款</a-anchor-link>
								</a-menu-item>
								<a-menu-item
									v-if="record.projectState === 'PARTIALLY_SHIPPED' || record.projectState === 'WAIT_DELIVER'"
								>
									<a-anchor-link @click="openAddProjectDelivery(record)" type="danger">添加发货单 </a-anchor-link>
								</a-menu-item>

								<a-menu-item v-if="hasPerm('bizSaleProjectCancel') && record.projectState === 'WAIT_DELIVER'">
									<a-anchor-link @click="cancelProject(record)">取消发货</a-anchor-link>
								</a-menu-item>

								<!--								<a-menu-item-->
								<!--									v-if="-->
								<!--										record.projectState !== 'FOLLOW' &&-->
								<!--										record.projectState !== 'DISCARD' &&-->
								<!--										record.projectState !== 'PENDING_APPROVAL'-->
								<!--									"-->
								<!--								>-->
								<!--									<a-anchor-link @click="openStartProjectReissueFlowForm(record)" type="danger">-->
								<!--										补发产品-->
								<!--									</a-anchor-link>-->
								<!--								</a-menu-item>-->
								<!--								<a-menu-item-->
								<!--									v-if="-->
								<!--										record.projectState !== 'FOLLOW' &&-->
								<!--										record.projectState !== 'DISCARD' &&-->
								<!--										record.projectState !== 'PENDING_APPROVAL'-->
								<!--									"-->
								<!--								>-->
								<!--									<a-anchor-link @click="openStartProjectReturnFlowFormRef(record)" type="danger">-->
								<!--										添加退货单-->
								<!--									</a-anchor-link>-->
								<!--								</a-menu-item>-->
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
	<start-project-reissue-flow-form ref="startProjectReissueFlowFormRef"></start-project-reissue-flow-form>
	<start-project-return-flow-form ref="startProjectReturnFlowFormRef"></start-project-return-flow-form>
	<projectExport ref="projectExportRef"></projectExport>
	<history-form @successful="tableRef.refresh()" ref="historyFormRef"></history-form>
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
	import Form from './form.vue'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import Detail from './detail.vue'
	import StartFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectInitFlowForm.vue'
	import StartPlayFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectPlayFlowForm.vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import StartProjectDeliveryFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectDeliveryFlowForm.vue'
	import StartProjectReissueFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectReissueFlowForm.vue'
	import { createVNode, useTemplateRef } from 'vue'
	import StartProjectReturnFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectReturnFlowForm.vue'
	import projectExport from './export/index.vue'
	import { Decimal } from 'decimal.js'
	import { required } from '@/utils/formRules'
	import { useLoading } from '@/composables/useLoading'
	import { useRoute } from 'vue-router'
	import HistoryForm from '@/views/biz/saleproject/form/historyForm.vue'
	import { ExclamationCircleOutlined } from '@ant-design/icons-vue'
	import { useOrg } from '@/composables/useOrg'
	import dayjs from '@/utils/dayjs'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const historyFormRef = useTemplateRef('historyFormRef')
	const publicFormRef = ref()
	const { message, modal, notification } = App.useApp()
	const openPublicModel = ref(false)
	const publicForm = ref({
		specimenCategory: '',
		specimenName: ''
	})
	const specimenOptions = ref([])
	specimenOptions.value = tool.dictListByPath('SALE_PROJECT', 'specimenCategory')
	const activeRecord = ref({})
	const route = useRoute()
	const showDiscard = ref(false)
	watch(showDiscard, (newVal, oldVal) => {
		tableRef.value.refresh()
	})

	const initParam = () => {
		if (route.query) {
			if (route.query.orgId) {
				searchFormState.value.orgId = route.query.orgId
			}
			if (route.query.startCompletionTime && route.query.endCompletionTime) {
				const startCompletionTime = dayjs(route.query.startCompletionTime).format('YYYY-MM-DD HH:mm:ss')
				const endCompletionTime = dayjs(route.query.endCompletionTime).format('YYYY-MM-DD HH:mm:ss')

				searchFormState.value.completionTime = [startCompletionTime, endCompletionTime]
			}

			if (route.query.startCreateTime && route.query.endCreateTime) {
				const startCreateTime = dayjs(route.query.startCreateTime).format('YYYY-MM-DD HH:mm:ss')
				const endCreateTime = dayjs(route.query.endCreateTime).format('YYYY-MM-DD HH:mm:ss')

				searchFormState.value.createTime = [startCreateTime, endCreateTime]
			}
			if (route.query.playState) {
				searchFormState.value.playState = route.query.playState.split(',')
			}
		}
	}

	onBeforeMount(() => {
		initParam()
	})

	onActivated(() => {
		initParam()
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

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const detailRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const startProjectDeliveryFlowForm = ref()
	const startProjectReissueFlowFormRef = useTemplateRef('startProjectReissueFlowFormRef')
	const projectExportRef = useTemplateRef('projectExportRef')
	const startProjectReturnFlowFormRef = useTemplateRef('startProjectReturnFlowFormRef')
	const startFlowFormRef = ref()
	const startPlayFlowFormRef = ref()

	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}

	const openExport = () => {
		projectExportRef.value.onOpen()
	}

	const columns = [
		{
			title: '项目名称',
			dataIndex: 'projectName',
			width: 300
		},
		{
			title: '项目状态',
			dataIndex: 'projectState',
			width: 100
		},
		// {
		// 	title: '付款状态',
		// 	dataIndex: 'playState',
		// 	align: 'center'
		// },
		{
			title: '收款进度',
			dataIndex: 'paymentProgress',
			align: 'center',
			width: 100
		},

		// {
		// 	title: '显示状态',
		// 	dataIndex: 'visibility',
		// 	width: 100
		// },
		{
			title: '合同金额',
			dataIndex: 'initPrice',
			width: 100
		},
		{
			title: '实际成交',
			dataIndex: 'dealPrice',
			width: 100
		},
		{
			title: '回扣',
			dataIndex: 'rebateAmount',
			width: 100
		},
		{
			title: '累计收款',
			dataIndex: 'amountCollected',
			width: 100
		},
		{
			title: '未付款',
			dataIndex: 'unAmountCollected',
			width: 100
		},

		{
			title: '客户来源',
			dataIndex: 'customerSourceType',
			ellipsis: true,
			width: 100
		},
		// {
		// 	title: '类别',
		// 	dataIndex: 'projectCategory'
		// },
		{
			title: '负责人',
			dataIndex: 'headName',
			width: 70
		},
		{
			title: '创建时间',
			dataIndex: 'createTime',
			width: 100,
			ellipsis: true
		}
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['bizSaleProjectDetail', 'bizSaleProjectCancel'])) {
		columns.push({
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			fixed: 'right',
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

		if (searchFormParam.projectState) {
			searchFormParam.projectState = searchFormParam.projectState.join(',')
		}

		const result = await bizSaleProjectApi.bizSaleProjectPage(
			Object.assign(
				parameter,
				searchFormParam,
				{
					showDiscard: true
				},
				{
					sortOrder: 'descend',
					sortField: 'createTime'
				},
				{
					projectState: 'DISCARD'
				}
			)
		)
		const processInfo = await bizProcessApi.bizProcessQuery({
			processCategory: 'Process_sale_project_play',
			variableName: 'projectId',
			findValue: 'amount',
			variable: result.records
				.map((value, index) => {
					return value.id
				})
				.join(',')
		})

		const processMap = {}
		const amountMap = {}
		processInfo.forEach((item) => {
			processMap[item.variable] = item.processIdList
			amountMap[item.variable] = Object.keys(item.variableMap)
				.reduce((pre, key) => {
					return pre.add(new Decimal(item.variableMap[key].amount))
				}, new Decimal(0))
				.toNumber()
		})

		const calculatePaymentProgressPercentage = (receivedAmount, totalAmount, decimalPlaces = 0) => {
			// 创建 Decimal 对象

			const received = new Decimal(receivedAmount)
			const total = new Decimal(totalAmount)

			// 计算收款进度
			const progress = received.div(total)

			// 转换为百分比
			const percentage = progress.mul(100)

			// 返回保留指定小数位数的百分比
			return Number(percentage.toFixed(0))
		}

		result.records = result.records.map((v) => {
			v.totalPrice = v.totalPrice ? v.totalPrice : 0
			v.rebateAmount = v.rebateAmount ? v.rebateAmount : 0
			let result = new Decimal(v.totalPrice).sub(new Decimal(v.rebateAmount))
			let dealPrice = result.toString()
			v.processIdList = processMap[v.id]
			v.auditAmount = amountMap[v.id]
			v.disabledPayment = false

			if (new Decimal(v.amountCollected).add(v.auditAmount).sub(new Decimal(v.totalPrice)).toNumber() >= 0) {
				v.disabledPayment = true
			}

			if (v.totalPrice === 0) {
				v.paymentProgress = v.playState === 'PAID' ? 100 : 0
				v.unAmountCollected = '--'
			} else {
				v.paymentProgress = calculatePaymentProgressPercentage(v.amountCollected, v.totalPrice)
				v.unAmountCollected = new Decimal(v.totalPrice).sub(new Decimal(v.amountCollected)).toFixed(2)
			}
			let obj = { ...v, dealPrice }

			return obj
		})
		return result
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
				id: record.id,
				repealContent: repealContent.value
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
	const restoreProject = (record) => {
		bizSaleProjectApi
			.cancelBizSaleProject({
				id: record.id
			})
			.then(() => {
				tableRef.value.refresh(true)
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

	const openStartProjectReissueFlowForm = (record) => {
		startProjectReissueFlowFormRef.value.onOpen(record)
	}

	const openStartProjectReturnFlowFormRef = (record) => {
		startProjectReturnFlowFormRef.value.onOpen(record)
	}

	const openAddProjectDelivery = (record) => {
		startProjectDeliveryFlowForm.value.onOpen(record)
	}

	const cancelProject = async (record) => {
		modal.confirm({
			title: '警告',
			icon: createVNode(ExclamationCircleOutlined),
			content: '确认取消销售项目吗？会同时关联删除开票信息？',
			async onOk() {
				await bizSaleProjectApi.cancelBizSaleProject({
					id: record.id
				})
				tableRef.value.refresh(true)
			},
			onCancel() {}
		})
	}

	const changeVisibility = async (record) => {
		openPublicModel.value = true
		activeRecord.value = record
	}
</script>
