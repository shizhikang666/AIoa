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
			:row-selection="rowSelection"
		>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex == 'projectName'">
					<a-typography-link
						@click="detailRef.onOpen(record)"
						v-if="hasPerm('bizSaleProjectEdit') && record.projectState === 'DISCARD'"
						type="danger"
						delete
						>{{ projectDisplayName(record) }}
					</a-typography-link>
					<a-typography-link @click="detailRef.onOpen(record)" v-else-if="hasPerm('bizSaleProjectEdit')">
						{{ projectDisplayName(record) }}
					</a-typography-link>
					<span v-else>{{ projectDisplayName(record) }}</span>
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
			</template>
		</s-table>
	</a-card>
	<Detail ref="detailRef" @successful="tableRef.refresh()"></Detail>
</template>
<script setup name="saleproject">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import Detail from '../detail.vue'

	const { rowSelection, travelRequired } = defineProps({
		rowSelection: {
			type: Object
		},
		travelRequired: {
			type: Boolean,
			default: false
		}
	})
	const formatDays = (value) => Number(value || 0).toFixed(1)
	const projectDisplayName = (record) => {
		if (!travelRequired) {
			return record.projectName
		}
		return `${record.projectName}（累计${formatDays(record.afterSalesTravelUsedDays)}天/计划${formatDays(
			record.travelDays
		)}天）`
	}

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const detailRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }

	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
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
					specialType: travelRequired ? undefined : 'PUBLIC_FOR_REIMBURSEMENT',
					travelRequired: travelRequired || undefined
				})
			)
			.then((data) => {
				return data
			})
	}
	// 重置

	const projectStateOptions = tool.dictListByPath(['SALE_PROJECT', 'SALE_PROJECT_STATE'])
	const playStateOptions = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_PLAY_STATE')
	const visibilityOptions = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_VISIBILITY')
	const projectCategoryOptions = tool.dictListByPath('SALE_PROJECT', 'PROJECT_CATEGORY')

	const changeVisibility = async (record) => {
		await bizSaleProjectApi.bizSaleProjectVisibilityEdit({
			projectId: record.id,
			visibilityState: record.visibility === 'PUBLIC' ? 'PRIVATE' : 'PUBLIC'
		})
		record.visibility = record.visibility === 'PUBLIC' ? 'PRIVATE' : 'PUBLIC'
	}
</script>
