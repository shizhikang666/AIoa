<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="请假类型" name="category">
						<a-select
							:options="categoryOptions"
							v-model:value="searchFormState.category"
							placeholder="请选择请假类型"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="天数" name="amount">
						<a-input v-model:value="searchFormState.amount" placeholder="请输入天数" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="请假人" name="name">
						<a-input v-model:value="searchFormState.name" placeholder="请假人" />
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
				<a-col :span="7" v-show="advanced">
					<a-form-item label="请假开始日期" name="startTime">
						<a-range-picker v-model:value="searchFormState.startTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="7" v-show="advanced">
					<a-form-item label="请假结束日期" name="endTime">
						<a-range-picker v-model:value="searchFormState.endTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					<a-button :loading="exportLoading" @click="exportExcel">
						<template #icon>
							<DownloadOutlined />
						</template>
						导出 Excel
					</a-button>
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
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('bizLeaveApplicationAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>
					<xn-batch-delete
						v-if="hasPerm('bizLeaveApplicationBatchDelete')"
						:selectedRowKeys="selectedRowKeys"
						@batchDelete="deleteBatchBizLeaveApplication"
					/>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'category'">
					{{ $TOOL.dictTypeDataByPathReturnEmpty('vacation', 'GoOut', record.category) }}
					{{ $TOOL.dictTypeDataByPathReturnEmpty('vacation', 'leave', record.category) }}
				</template>
				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link
						v-if="record.processId !== 'Process_sys'"
						@click="processDetailsRef.onOpen({ instanceId: record.processId })"
					>
						{{ record.processId }}
					</a-typography-link>
				</template>

				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a @click="formRef.onOpen(record)" v-if="hasPerm('bizLeaveApplicationEdit')">编辑</a>
						<a-divider
							type="vertical"
							v-if="hasPerm(['bizLeaveApplicationEdit', 'bizLeaveApplicationDelete'], 'and')"
						/>
						<a-popconfirm title="确定要删除吗？" @confirm="deleteBizLeaveApplication(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('bizLeaveApplicationDelete')">删除 </a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<processDetails ref="processDetailsRef"></processDetails>
</template>

<script setup name="bizleaveapplication">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizLeaveApplicationApi from '@/api/biz/bizLeaveApplicationApi'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import { useTemplateRef } from 'vue'
	import { useOrg } from '@/composables/useOrg'
	import { useLoading } from '@/composables/useLoading'
	import { message } from 'ant-design-vue'
	import ExcelJS from 'exceljs'
	import { saveAs } from 'file-saver'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData()
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const processDetailsRef = useTemplateRef('processDetailsRef')
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const EXPORT_PAGE_SIZE = 200
	const EXPORT_MAX_ROWS = 10000
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '请假人',
			dataIndex: 'name'
		},
		{
			title: '流程iD',
			dataIndex: 'processId',
			ellipsis: true
		},
		{
			title: '请假类型',
			dataIndex: 'category'
		},
		{
			title: '天数',
			dataIndex: 'amount'
		},
		{
			title: '请假原因',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '请假开始日期',
			dataIndex: 'startTime',
			ellipsis: true
		},
		{
			title: '请假结束日期',
			dataIndex: 'endTime',
			ellipsis: true
		}
	]
	// 操作栏通过权限判断是否显示
	// if (hasPerm(['bizLeaveApplicationEdit', 'bizLeaveApplicationDelete'])) {
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
	const buildSearchParams = () => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// startTime范围查询条件重载
		if (searchFormParam.startTime) {
			searchFormParam.startStartTime = searchFormParam.startTime[0]
			searchFormParam.endStartTime = searchFormParam.startTime[1]
			delete searchFormParam.startTime
		}
		// endTime范围查询条件重载
		if (searchFormParam.endTime) {
			searchFormParam.startEndTime = searchFormParam.endTime[0]
			searchFormParam.endEndTime = searchFormParam.endTime[1]
			delete searchFormParam.endTime
		}
		return searchFormParam
	}
	const loadData = (parameter) => {
		const searchFormParam = buildSearchParams()
		return bizLeaveApplicationApi.bizLeaveApplicationPage(Object.assign(parameter, searchFormParam)).then((data) => {
			return data
		})
	}
	const excelText = (value) => {
		const text = value === null || value === undefined ? '' : String(value)
		return /^[=+\-@]/.test(text) ? `'${text}` : text
	}
	const categoryLabel = (category) => {
		return (
			tool.dictTypeDataByPathReturnEmpty('vacation', 'GoOut', category) ||
			tool.dictTypeDataByPathReturnEmpty('vacation', 'leave', category) ||
			String(category ?? '')
		)
	}
	const fetchExportRecords = async () => {
		const filters = buildSearchParams()
		const firstPage = await bizLeaveApplicationApi.bizLeaveApplicationPage({
			...filters,
			current: 1,
			size: EXPORT_PAGE_SIZE
		})
		const total = Number(firstPage?.total ?? 0)
		if (total > EXPORT_MAX_ROWS) {
			message.warning(`查询结果超过 ${EXPORT_MAX_ROWS} 条，请缩小筛选范围后再导出`)
			return null
		}

		const records = Array.isArray(firstPage?.records) ? [...firstPage.records] : []
		const pageCount = Math.ceil(total / EXPORT_PAGE_SIZE)
		for (let current = 2; current <= pageCount; current += 1) {
			const pageData = await bizLeaveApplicationApi.bizLeaveApplicationPage({
				...filters,
				current,
				size: EXPORT_PAGE_SIZE
			})
			const pageRecords = Array.isArray(pageData?.records) ? pageData.records : []
			records.push(...pageRecords)
		}

		if (records.length !== total) {
			message.error('请假记录导出失败，请稍后重试')
			return null
		}
		return records
	}
	const { load: exportExcel, loading: exportLoading } = useLoading(async () => {
		const records = await fetchExportRecords()
		if (records === null) {
			return
		}
		if (records.length === 0) {
			message.warning('暂无可导出的请假记录')
			return
		}

		const workbook = new ExcelJS.Workbook()
		workbook.creator = '福地科技'
		const worksheet = workbook.addWorksheet('请假记录表')
		worksheet.columns = [
			{ header: '请假人', key: 'name', width: 18 },
			{ header: '所属组织', key: 'orgName', width: 24 },
			{ header: '流程 ID', key: 'processId', width: 26 },
			{ header: '请假类型', key: 'category', width: 16 },
			{ header: '天数', key: 'amount', width: 12 },
			{ header: '请假原因', key: 'remark', width: 36 },
			{ header: '请假开始日期', key: 'startTime', width: 22 },
			{ header: '请假结束日期', key: 'endTime', width: 22 }
		]
		worksheet.views = [{ state: 'frozen', ySplit: 1 }]
		worksheet.getRow(1).eachCell((cell) => {
			cell.font = { bold: true }
			cell.alignment = { horizontal: 'center', vertical: 'middle' }
		})
		records.forEach((record) => {
			worksheet.addRow({
				name: excelText(record.name),
				orgName: excelText(record.orgName),
				processId: excelText(record.processId),
				category: excelText(categoryLabel(record.category)),
				amount: excelText(record.amount),
				remark: excelText(record.remark),
				startTime: excelText(record.startTime),
				endTime: excelText(record.endTime)
			})
		})
		worksheet.eachRow((row, rowNumber) => {
			row.height = 20
			if (rowNumber > 1) {
				row.eachCell((cell) => {
					cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true }
				})
			}
		})

		const buffer = await workbook.xlsx.writeBuffer()
		const file = new Blob([buffer], {
			type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		})
		saveAs(file, '请假记录表.xlsx')
	})
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteBizLeaveApplication = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizLeaveApplicationApi.bizLeaveApplicationDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchBizLeaveApplication = (params) => {
		bizLeaveApplicationApi.bizLeaveApplicationDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
	const categoryOptions = computed(() => {
		let options = []
		options = options.concat(tool.dictListByPath('vacation', 'leave'))
		options = options.concat(tool.dictListByPath('vacation', 'GoOut'))
		console.log(options)
		return options
	})
</script>
