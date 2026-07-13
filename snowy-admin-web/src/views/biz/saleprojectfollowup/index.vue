<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<!--				<a-col :span="6">-->
				<!--					<a-form-item label="跟进项目编号" name="projectId">-->
				<!--						<a-input v-model:value="searchFormState.projectId" placeholder="请输入跟进项目编号" />-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->
				<a-col :span="6">
					<a-form-item label="跟进时间" name="followUpTime">
						<a-range-picker v-model:value="searchFormState.followUpTime" value-format="YYYY-MM-DD HH:mm:ss" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="跟进类型" name="category">
						<a-select
							v-model:value="searchFormState.category"
							placeholder="请选择跟进类型"
							:options="categoryOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="内容" name="content">
						<a-input v-model:value="searchFormState.content" placeholder="请输入内容" />
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
				<template v-if="column.dataIndex === 'projectName'">
					<a-typography-link
						@click="
							projectDetailsRef.onOpen({
								id: record.projectId
							})
						"
						>{{ record.projectName }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'category'">
					{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'FOLLOW_UP_CATEGORY', record.category) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('saleProjectFollowUpEdit')">编辑</a>
						<a-divider
							type="vertical"
							v-if="hasPerm(['saleProjectFollowUpEdit', 'saleProjectFollowUpDelete'], 'and')"
						/>
						<a-popconfirm title="确定要删除吗？" @confirm="deleteSaleProjectFollowUp(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('saleProjectFollowUpDelete')">删除 </a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<projectDetails ref="projectDetailsRef"></projectDetails>
</template>

<script setup name="saleprojectfollowup">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import saleProjectFollowUpApi from '@/api/biz/saleProjectFollowUpApi'
	import projectDetails from '@/views/biz/saleproject/detail.vue'
	import { useTemplateRef } from 'vue'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const projectDetailsRef = useTemplateRef('projectDetailsRef')
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		// {
		// 	title: '跟进项目编号',
		// 	dataIndex: 'projectId'
		// },
		{
			title: '跟进项目',
			dataIndex: 'projectName',
			ellipsis: true
		},

		{
			title: '创建人',
			dataIndex: 'createUserName',
			width: 100
		},

		{
			title: '跟进类型',
			dataIndex: 'category',
			width: 100
		},
		{
			title: '内容',
			dataIndex: 'content',
			ellipsis: true
		},
		{
			title: '跟进时间',
			dataIndex: 'followUpTime'
		}
	]
	// 操作栏通过权限判断是否显示
	// if (hasPerm(['saleProjectFollowUpEdit', 'saleProjectFollowUpDelete'])) {
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
		return saleProjectFollowUpApi.saleProjectFollowUpPage(Object.assign(parameter, searchFormParam)).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteSaleProjectFollowUp = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		saleProjectFollowUpApi.saleProjectFollowUpDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchSaleProjectFollowUp = (params) => {
		saleProjectFollowUpApi.saleProjectFollowUpDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
	const categoryOptions = tool.dictList('SALE_PROJECT')
</script>
