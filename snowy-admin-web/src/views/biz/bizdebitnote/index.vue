<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="账户名称" name="accountName">
						<a-input placeholder="账户名称" v-model:value="searchFormState.accountName"></a-input>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="关键字查询" name="remark">
						<a-input placeholder="关键字查询" v-model:value="searchFormState.remark"></a-input>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="结算状态" name="playStatus">
						<a-select
							v-model:value="searchFormState.playStatus"
							placeholder="请选择结算状态"
							:options="playStatusOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker v-model:value="searchFormState.createTime" value-format="YYYY-MM-DD HH:mm:ss" show-time />
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
			bordered
			:row-selection="options.rowSelection"
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #operator class="table-operator">
				<a-space :size="50">
					<span>
						总借出：
						<a-typography-text type="warning">{{ totalObject.amount }}</a-typography-text>
					</span>
					<span>
						欠款：
						<a-typography-text type="danger">{{ totalObject.notAmount }}</a-typography-text>
					</span>

					<a-button type="primary" @click="openFastAmount" v-if="hasPerm('bizDebitNoteAdd')"> 快速结算 </a-button>
					<a-button type="primary" @click="exportExcel"> 导出</a-button>

					<a-button @click="addFormRef.onOpen()" v-if="hasPerm('bizDebitNoteAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						历史借款录入
					</a-button>
				</a-space>
			</template>

			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'playStatus'">
					{{ $TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status', record.playStatus) }}
				</template>

				<template v-else-if="column.dataIndex === 'expenditureRecordId'">
					{{ record.expenditureRecordId ? record.expenditureRecordId : '---' }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-popconfirm title="确定要标记已结算吗？" @confirm="mark(record)">
							<a-button
								:disabled="record.playStatus === 'AlreadySettled'"
								type="link"
								danger
								size="small"
								v-if="hasPerm('bizDebitNoteDelete')"
							>
								标记已结算
							</a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>

	<Form ref="formRef" @successful="tableRef.refresh()" />
	<AddForm ref="addFormRef" @successful="tableRef.refresh()"></AddForm>
</template>

<script setup name="bizdebitnote">
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizDebitNoteApi from '@/api/biz/bizDebitNoteApi'
	import tool from '@/utils/tool'
	import { Decimal } from 'decimal.js'
	import { message } from 'ant-design-vue'
	import AddForm from '@/views/biz/bizdebitnote/addForm.vue'
	import { useTemplateRef } from 'vue'
	import { useLoading } from '@/composables/useLoading'
	import ExcelJS from 'exceljs'
	import { saveAs } from 'file-saver'

	const addFormRef = useTemplateRef('addFormRef')
	const playStatusOptions = tool.dictListByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status')
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const columns = [
		{
			title: '支出账户',
			dataIndex: 'accountName',
			ellipsis: true
		},
		{
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '结算状态',
			dataIndex: 'playStatus'
		},
		{
			title: '借款金额',
			dataIndex: 'amount'
		},
		{
			title: '已还款金额',
			dataIndex: 'settlementAmount'
		},
		{
			title: '创建时间',
			dataIndex: 'createTime'
		}
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['bizDebitNoteEdit', 'bizDebitNoteDelete'])) {
		columns.push({
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 150
		})
	}
	const selectedRowKeys = ref([])
	const selectedRows = ref([])
	const allList = ref([])

	const totalObject = computed(() => {
		return allList.value.reduce(
			(pre, currentValue) => {
				if (currentValue.playStatus === 'Unsettled') {
					const count = new Decimal(currentValue.amount).sub(currentValue.settlementAmount)
					pre.notAmount = pre.notAmount.add(count)
				}
				pre.amount = pre.amount.add(currentValue.amount)
				pre.settlementAmount = pre.settlementAmount.add(currentValue.settlementAmount)

				return pre
			},
			{
				amount: new Decimal(0),
				settlementAmount: new Decimal(0),
				notAmount: new Decimal(0)
			}
		)
	})

	// 列表选择配置
	const options = {
		// columns数字类型字段加入 needTotal: true 可以勾选自动算账
		alert: {
			show: true,
			clear: () => {
				selectedRowKeys.value = ref([])
				selectedRows.value = ref([])
			}
		},
		rowSelection: {
			onChange: (selectedRowKey, selectedRow) => {
				selectedRowKeys.value = selectedRowKey
				selectedRows.value = selectedRow
			},
			getCheckboxProps: (record) => ({
				disabled: record.playStatus === 'AlreadySettled' // Column configuration not to be checked
			})
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
		allList.value = await bizDebitNoteApi.bizDebitNoteList(
			Object.assign(parameter, searchFormParam, {
				category: 'loan'
			})
		)
		return await bizDebitNoteApi.bizDebitNotePage(
			Object.assign(parameter, searchFormParam, {
				sortField: 'playStatus',
				category: 'loan'
			})
		)
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const mark = (record) => {
		let params = {
			id: record.id
		}
		bizDebitNoteApi.mark(params).then(() => {
			tableRef.value.refresh(true)
		})
	}

	const openFastAmount = () => {
		if (selectedRows.value.length == 0) {
			message.warning('请选择一条或多条数据')
			return
		}
		formRef.value.onOpen({
			records: selectedRows.value
		})
	}

	const {
		load: exportExcel,
		loading: exportLoading,
		ExportError
	} = useLoading(async () => {
		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')

		// 定义列标题
		const column = ['支出账户', '备注', '借款金额', '已还款金额', '结算状态', '创建日期']
		let maxLength = column.map((v) => {
			return v.length
		})
		// 添加标题行
		worksheet.addRow(column)

		// 设置标题行样式
		worksheet.getRow(1).eachCell((cell) => {
			cell.font = { bold: true } // 加粗
			cell.alignment = { horizontal: 'center', vertical: 'middle' } // 居中
		})

		// 添加数据行
		const data = allList.value.map((v) => {
			return [
				v.accountName,
				v.remark,
				v.amount,
				v.settlementAmount,
				tool.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status', v.playStatus),
				v.createTime
			]
		})
		data.forEach((row) => {
			row.forEach((v, index) => {
				if (v && v.length > maxLength[index]) {
					maxLength[index] = v.length
				}
			})
			worksheet.addRow(row)
		})

		// 设置数据行样式
		worksheet.eachRow((row, rowNumber) => {
			if (rowNumber > 1) {
				// 跳过标题行
				row.eachCell((cell) => {
					cell.alignment = { horizontal: 'center', vertical: 'middle' } // 居中
				})
			}
		})

		// 设置列宽
		worksheet.columns = column.map((col, i) => ({
			header: col,
			key: col,
			width: maxLength[i] + 20 // 动态列宽
		}))

		// 设置行高
		worksheet.eachRow((row) => {
			row.height = 20 // 固定行高
		})

		// 生成 Excel 文件
		const buffer = await workbook.xlsx.writeBuffer()
		const file = new Blob([buffer], { type: 'application/octet-stream' })
		saveAs(file, 'export.xlsx')
	})

	// 批量删除
	// const deleteBatchBizDebitNote = (params) => {
	// 	bizDebitNoteApi.bizDebitNoteDelete(params).then(() => {
	// 		tableRef.value.clearRefreshSelected()
	// 	})
	// }
</script>
