<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-form-item label="收款账号名称" name="accountName">
					<a-input v-model:value="searchFormState.accountName" placeholder="请输入收款账号名称" />
				</a-form-item>
				<a-col :span="6">
					<a-form-item label="结算分类" name="settlementCategory">
						<a-select
							placeholder="选择结算类型"
							v-model:value="searchFormState.settlementCategory"
							:options="settlementCategoryList"
						></a-select>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="打款款人" name="payer">
						<a-input v-model:value="searchFormState.payer" placeholder="请输入打款款人" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="打款银行行" name="bankName">
						<a-input v-model:value="searchFormState.bankName" placeholder="请输入打款银行行" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="银行账户" name="bankAccount">
						<a-input v-model:value="searchFormState.bankAccount" placeholder="请输入银行账户" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="备注" name="remark">
						<a-input v-model:value="searchFormState.remark" placeholder="请输入备注" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="打款时间" name="payerTime">
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.payerTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="金额" name="amount">
						<a-input v-model:value="searchFormState.amount" placeholder="请输入打款金额" />
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
					<a-button :loading="exportRecordLoading" @click="exportRecord">导出</a-button>

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
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
			:row-selection="options.rowSelection"
		>
			<template #operator class="table-operator">
				<!--				<a-space>-->
				<!--					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('bizPaymentRecordAdd')">-->
				<!--						<template #icon><plus-outlined /></template>-->
				<!--						新增-->
				<!--					</a-button>-->
				<!--					<xn-batch-delete-->
				<!--						v-if="hasPerm('bizPaymentRecordBatchDelete')"-->
				<!--						:selectedRowKeys="selectedRowKeys"-->
				<!--						@batchDelete="deleteBatchBizPaymentRecord"-->
				<!--					/>-->
				<!--				</a-space>-->
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link
						v-if="record.processId !== 'Process_sys'"
						@click="processDetailsRef.onOpen({ instanceId: record.processId })"
					>
						{{ record.processId }}
					</a-typography-link>
					<template v-else>系统流程</template>
				</template>

				<template v-if="column.dataIndex === 'settlementCategory'">
					{{
						tool.dictTypeDataByPath(
							'SETTLEMENT_ACCOUNT',
							'SETTLEMENT_CATEGORY',
							'INCOME_CATEGORY',
							...record.settlementCategory.split('/')
						)
					}}
				</template>

				<!--				<template v-if="column.dataIndex === 'action'">-->
				<!--					<a-space>-->
				<!--						<a @click="formRef.onOpen(record)" v-if="hasPerm('bizPaymentRecordEdit')">编辑</a>-->
				<!--						<a-divider type="vertical" v-if="hasPerm(['bizPaymentRecordEdit', 'bizPaymentRecordDelete'], 'and')" />-->
				<!--						<a-popconfirm title="确定要删除吗？" @confirm="deleteBizPaymentRecord(record)">-->
				<!--							<a-button type="link" danger size="small" v-if="hasPerm('bizPaymentRecordDelete')">删除</a-button>-->
				<!--						</a-popconfirm>-->
				<!--					</a-space>-->
				<!--				</template>-->
			</template>
		</s-table>
	</a-card>
	<processDetails ref="processDetailsRef"></processDetails>
	<Form ref="formRef" @successful="tableRef.refresh()" />
</template>

<script setup name="paymentrecord">
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizPaymentRecordApi from '@/api/biz/bizPaymentRecordApi'
	import tool from '@/utils/tool'
	import { useTemplateRef } from 'vue'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import { useRoute } from 'vue-router'
	import { useLoading } from '@/composables/useLoading'
	import bizExpenditureRecordApi from '@/api/biz/bizExpenditureRecordApi'
	import ExcelJS from 'exceljs'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const processDetailsRef = useTemplateRef('processDetailsRef')
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const settlementCategoryList = tool.dictListByPath(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'INCOME_CATEGORY'])
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '收入账号',
			dataIndex: 'accountName'
		},
		{
			title: '流程实例编号',
			dataIndex: 'processId',
			ellipsis: true
		},
		{
			title: '结算分类',
			dataIndex: 'settlementCategory',
			width: 200
		},
		{
			title: '金额',
			dataIndex: 'amount',
			width: 100
		},
		// {
		// 	title: '打款款人',
		// 	dataIndex: 'payer'
		// },
		// {
		// 	title: '打款银行行',
		// 	dataIndex: 'bankName'
		// },
		// {
		// 	title: '银行账户',
		// 	dataIndex: 'bankAccount'
		// },
		{
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '打款时间',
			dataIndex: 'payerTime'
		}
	]
	// 操作栏通过权限判断是否显示
	// if (hasPerm(['bizPaymentRecordEdit', 'bizPaymentRecordDelete'])) {
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
	const route = useRoute()
	const loadData = (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// payerTime范围查询条件重载
		if (searchFormParam.payerTime) {
			searchFormParam.startPayerTime = searchFormParam.payerTime[0]
			searchFormParam.endPayerTime = searchFormParam.payerTime[1]
			delete searchFormParam.payerTime
		}

		let routerParam = {}
		if (route.query) {
			routerParam = { ...route.query }
		}

		return bizPaymentRecordApi
			.bizPaymentRecordPage(Object.assign(parameter, searchFormParam, routerParam))
			.then((data) => {
				return data
			})
	}

	const { load: exportRecord, loading: exportRecordLoading } = useLoading(async () => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// payerTime范围查询条件重载
		if (searchFormParam.payerTime) {
			searchFormParam.startPayerTime = searchFormParam.payerTime[0]
			searchFormParam.endPayerTime = searchFormParam.payerTime[1]
			delete searchFormParam.payerTime
		}

		let routerParam = {}
		if (route.query) {
			routerParam = { ...route.query }
		}

		const allList = await bizPaymentRecordApi.bizPaymentRecordListDetails(Object.assign(searchFormParam, routerParam))

		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')

		// 定义列标题
		const column = ['收入账户', '流程实例编号', '结算分类', '金额', '打款人', '备注', '收款时间']
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
		const data = allList.map((v) => {
			return [
				v.accountName,
				v.processId,
				tool.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'INCOME_CATEGORY', v.settlementCategory),
				v.amount,
				v.payer,
				v.remark,
				v.payerTime
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
		saveAs(file, '收款记录.xlsx')
	})

	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteBizPaymentRecord = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizPaymentRecordApi.bizPaymentRecordDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchBizPaymentRecord = (params) => {
		bizPaymentRecordApi.bizPaymentRecordDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
</script>
