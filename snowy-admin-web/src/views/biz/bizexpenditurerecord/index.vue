<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="支出账号名称" name="accountName">
						<a-input v-model:value="searchFormState.accountName" placeholder="请输入支出账号编号" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="结算分类" name="settlementCategory">
						<a-select
							mode="multiple"
							placeholder="选择结算类型"
							v-model:value="searchFormState.settlementCategory"
							:options="settlementCategoryList"
						></a-select>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="收款人" name="payer">
						<a-input v-model:value="searchFormState.payer" placeholder="请输入收款人" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="开户行" name="bankName">
						<a-input v-model:value="searchFormState.bankName" placeholder="请输入开户行" />
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
					<a-form-item label="付款时间" name="payerTime">
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.payerTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="支出金额" name="amount">
						<a-input v-model:value="searchFormState.amount" placeholder="请输入支出金额" />
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
				<a-space>
					<!--					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('bizExpenditureRecordAdd')">-->
					<!--						<template #icon><plus-outlined /></template>-->
					<!--						新增-->
					<!--					</a-button>-->
					<!--					<xn-batch-delete-->
					<!--						v-if="hasPerm('bizExpenditureRecordBatchDelete')"-->
					<!--						:selectedRowKeys="selectedRowKeys"-->
					<!--						@batchDelete="deleteBatchBizExpenditureRecord"-->
					<!--					/>-->
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'settlementCategory'">
					{{
						tool.dictTypeDataByPath(
							'SETTLEMENT_ACCOUNT',
							'SETTLEMENT_CATEGORY',
							'PAY_CATEGORY',
							record.settlementCategory
						)
					}}
				</template>
				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link
						v-if="record.processId !== 'Process_sys'"
						@click="processDetailsRef.onOpen({ instanceId: record.processId })"
					>
						{{ record.processId }}
					</a-typography-link>
					<template v-else>系统流程</template>
				</template>

				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-button
							type="link"
							:disabled="isDisabled(record.settlementCategory)"
							@click="formRef.onOpen(record)"
							v-if="hasPerm('bizExpenditureRecordEdit')"
							>编辑
						</a-button>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<processDetails ref="processDetailsRef"></processDetails>
</template>

<script setup name="bizexpenditurerecord">
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizExpenditureRecordApi from '@/api/biz/bizExpenditureRecordApi'
	import tool from '@/utils/tool'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import { useTemplateRef } from 'vue'
	import { useRoute } from 'vue-router'
	import { useLoading } from '@/composables/useLoading'
	import ExcelJS from 'exceljs'
	import { saveAs } from 'file-saver'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const processDetailsRef = useTemplateRef('processDetailsRef')
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const settlementCategoryList = tool.dictListByPath(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY'])

	const columns = [
		{
			title: '支出账号',
			dataIndex: 'accountName',
			ellipsis: true
		},
		{
			title: '流程实例编号',
			dataIndex: 'processId',
			ellipsis: true
		},
		{
			title: '结算分类',
			dataIndex: 'settlementCategory'
		},
		{
			title: '支出金额',
			dataIndex: 'amount'
		},
		{
			title: '收款人',
			dataIndex: 'payer'
		},
		{
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '开户行',
			dataIndex: 'bankName',
			ellipsis: true
		},
		{
			title: '银行账户',
			dataIndex: 'bankAccount',
			ellipsis: true
		},

		{
			title: '付款时间',
			dataIndex: 'payerTime'
		},
		{
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: '100px'
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
	const route = useRoute()
	const loadData = (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// payerTime范围查询条件重载
		if (searchFormParam.payerTime) {
			searchFormParam.startPayerTime = searchFormParam.payerTime[0]
			searchFormParam.endPayerTime = searchFormParam.payerTime[1]
			delete searchFormParam.payerTime
		}

		if (searchFormParam.settlementCategory) {
			searchFormParam.settlementCategory = searchFormParam.settlementCategory.join(',')
		}

		let routerParam = {}
		if (route.query) {
			routerParam = { ...route.query }
		}

		return bizExpenditureRecordApi
			.bizExpenditureRecordPage(Object.assign(parameter, searchFormParam, routerParam))
			.then((data) => {
				return data
			})
	}

	//导出
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
		const allList = await bizExpenditureRecordApi.bizExpenditureRecordListDetails(
			Object.assign(searchFormParam, routerParam)
		)

		const workbook = new ExcelJS.Workbook()
		const worksheet = workbook.addWorksheet('Sheet1')

		// 定义列标题
		const column = [
			'支出账户',
			'流程实例编号',
			'结算分类',
			'支出金额',
			'收款人',
			'备注',
			'开户行',
			'银行账户',
			'付款时间'
		]
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
				tool.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY', v.settlementCategory),
				v.amount,
				v.payer,
				v.remark,
				v.bankName,
				v.bankAccount,
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
		saveAs(file, '支出记录.xlsx')
	})

	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}

	const isDisabled = (str) => {
		return ['ReturnAndRefund', 'GOODS_EXPENDITURE', 'CUSTOMER_REBATE', 'repayment', 'proxyPayment'].some(
			(item) => item === str
		)
	}

	// 删除
	const deleteBizExpenditureRecord = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizExpenditureRecordApi.bizExpenditureRecordDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchBizExpenditureRecord = (params) => {
		bizExpenditureRecordApi.bizExpenditureRecordDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
</script>
