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
						总代收款：
						<a-typography-text type="warning">{{ totalObject.amount }}</a-typography-text>
					</span>
					<span>
						总未还代收款：
						<a-typography-text type="danger">{{ totalObject.notAmount }}</a-typography-text>
					</span>

					<a-button type="primary" @click="openFastAmount" v-if="hasPerm('bizDebitNoteAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						快速结算
					</a-button>
					<a-button :loading="exportRecordLoading" @click="exportRecord"> 导出 全部</a-button>
				</a-space>
				<!--				<a-space>-->
				<!--					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('bizCollectionReceiptAdd')">-->
				<!--						<template #icon>-->
				<!--							<plus-outlined />-->
				<!--						</template>-->
				<!--						新增-->
				<!--					</a-button>-->
				<!--					<xn-batch-delete-->
				<!--						v-if="hasPerm('bizCollectionReceiptBatchDelete')"-->
				<!--						:selectedRowKeys="selectedRowKeys"-->
				<!--						@batchDelete="deleteBatchBizCollectionReceipt"-->
				<!--					/>-->
				<!--				</a-space>-->
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'playStatus'">
					{{ $TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status', record.playStatus) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-popconfirm title="确定要标记已结算吗？" @confirm="mark(record)">
							<a-button
								:disabled="record.playStatus === 'AlreadySettled'"
								type="link"
								danger
								size="small"
								v-if="hasPerm('bizCollectionReceiptEdit')"
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
</template>

<script setup name="bizcollectionreceipt">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizCollectionReceiptApi from '@/api/biz/bizCollectionReceiptApi'
	import { Decimal } from 'decimal.js'
	import { message } from 'ant-design-vue'
	import { useLoading } from '@/composables/useLoading'
	import bizPaymentRecordApi from '@/api/biz/bizPaymentRecordApi'
	import ExcelJS from 'exceljs'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const columns = [
		{
			title: '收款账户',
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
			title: '代收款金额',
			dataIndex: 'amount'
		},
		{
			title: '已结算金额',
			dataIndex: 'settlementAmount'
		}
	]
	// 操作栏通过权限判断是否显示
	if (hasPerm(['bizCollectionReceiptEdit', 'bizCollectionReceiptDelete'])) {
		columns.push({
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 150
		})
	}
	const selectedRowKeys = ref([])
	const selectedRows = ref([])
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

	const loadData = async (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)

		allList.value = await bizCollectionReceiptApi.bizCollectionReceiptList(Object.assign(parameter, searchFormParam))
		return await bizCollectionReceiptApi.bizCollectionReceiptPage(
			Object.assign(parameter, searchFormParam, {
				sortField: 'playStatus'
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

		bizCollectionReceiptApi.mark(params).then(() => {
			tableRef.value.refresh(true)
		})
	}

	const playStatusOptions = tool.dictListByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status')

	const openFastAmount = () => {
		if (selectedRows.value.length == 0) {
			message.warning('请选择一条或多条数据')
			return
		}
		formRef.value.onOpen({
			records: selectedRows.value
		})
	}

	const { load: exportRecord, loading: exportRecordLoading } = useLoading(async () => {
		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')

		// 定义列标题
		const column = ['账户名称', '代收款金额', '结算状态', '已结算金额', '备注', '收款时间', '录入系统时间', '创建人']
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
				v.amount,
				tool.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status', v.playStatus),
				v.settlementAmount,
				v.remark,
				v.payerTime,
				v.createTime,
				v.createUserName
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
		saveAs(file, '代收款单管理.xlsx')
	})
</script>
