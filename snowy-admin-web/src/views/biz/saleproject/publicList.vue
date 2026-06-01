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
				<!--				<a-col :span="6">-->
				<!--					<a-form-item label="项目状态" name="projectState">-->
				<!--						<a-select-->
				<!--							mode="multiple"-->
				<!--							v-model:value="searchFormState.projectState"-->
				<!--							placeholder="请选择项目状态"-->
				<!--							:options="projectStateOptions"-->
				<!--						/>-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->

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
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.createTime" show-time />
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
			<template #operator class="table-operator"></template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex == 'projectName'">
					<a-typography-link @click="openDetail(record)" v-if="record.projectState === 'DISCARD'" type="danger" delete
						>{{ record.projectName }}
					</a-typography-link>
					<a-typography-link @click="openDetail(record)" v-else>{{ record.projectName }}</a-typography-link>
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
				<template v-if="column.dataIndex === 'visibility'"></template>
				<template v-if="column.dataIndex === 'projectCategory'">
					{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'PROJECT_CATEGORY', record.projectCategory) }}
				</template>
				<template v-if="column.dataIndex === 'specimenCategory'">
					{{
						record.specimenCategory
							? $TOOL.dictTypeDataByPath('SALE_PROJECT', 'specimenCategory', record.specimenCategory)
							: ''
					}}
				</template>

				<!--				<template v-if="column.dataIndex === 'action'">-->
				<!--					<a-space>-->
				<!--						<a @click="formRef.onOpen(record)" v-if="hasPerm('bizSaleProjectEdit')">编辑</a>-->
				<!--						<a-divider type="vertical" v-if="hasPerm(['bizSaleProjectEdit', 'bizSaleProjectDelete'], 'and')" />-->
				<!--						&lt;!&ndash;						<a-popconfirm title="确定要删除吗？" @confirm="deleteBizSaleProject(record)">&ndash;&gt;-->
				<!--						&lt;!&ndash;							<a-button :disabled="record.projectState!='FOLLOW'" type="link" danger size="small" v-if="hasPerm('bizSaleProjectDelete')">删除 </a-button>&ndash;&gt;-->
				<!--						&lt;!&ndash;						</a-popconfirm>&ndash;&gt;-->

				<!--						<a-popconfirm title="确定要作废吗？" @confirm="repealBizSaleProject(record)">-->
				<!--							<a-button :disabled="record.projectState!='FOLLOW'" type="link" danger size="small" v-if="hasPerm('bizSaleProjectRepeal')">作废 </a-button>-->
				<!--						</a-popconfirm>-->

				<!--					</a-space>-->
				<!--					<a-divider type="vertical" v-if="hasPerm(['bizSaleProjectDetail'])" />-->

				<!--				</template>-->
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<Detail ref="detailRef" @successful="tableRef.refresh()"></Detail>
</template>
<script setup name="publicList">
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

	const openDetail = (record) => {
		if (hasPerm('bizSaleProjectPublicDetail')) {
			detailRef.value.onOpen({ id: record.id })
		}
	}

	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '项目名称',
			dataIndex: 'projectName',
			width: 300
		},
		{
			title: '项目编号',
			dataIndex: 'projectCode',
			ellipsis: true,
			width: 200
		},
		// {
		// 	title: '项目状态',
		// 	dataIndex: 'projectState'
		// },
		{
			title: '标底类型',
			dataIndex: 'specimenCategory',
			width: 100
		},
		{
			title: '品牌',
			dataIndex: 'specimenName',
			width: 100
		},

		{
			title: '类别',
			dataIndex: 'projectCategory',
			width: 100,
			ellipsis: true
		},
		{
			title: '项目负责人',
			dataIndex: 'headName',
			width: 100
		},
		{
			title: '创建时间',
			dataIndex: 'createTime',
			ellipsis: true
		}
	]
	// 操作栏通过权限判断是否显示

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
		if (searchFormParam.playState) {
			searchFormParam.playState = searchFormParam.playState.join(',')
		}

		if (searchFormParam.projectState) {
			searchFormParam.projectState = searchFormParam.projectState.join(',')
		}
		return bizSaleProjectApi
			.bizSaleProjecPublicPage(
				Object.assign(parameter, searchFormParam, {
					projectState: 'FOLLOW',
					sortOrder: 'descend',
					sortField: 'createTime'
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

	const repealBizSaleProject = (record) => {
		let params = [
			{
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

	const changeVisibility = async (record) => {
		await bizSaleProjectApi.bizSaleProjectVisibilityEdit({
			projectId: record.id,
			visibilityState: record.visibility === 'PUBLIC' ? 'PRIVATE' : 'PUBLIC'
		})
		record.visibility = record.visibility === 'PUBLIC' ? 'PRIVATE' : 'PUBLIC'
	}
</script>
