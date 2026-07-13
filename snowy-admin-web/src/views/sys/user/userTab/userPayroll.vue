<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="月份" name="salaryTime">
						<a-date-picker
							value-format="YYYY-MM-DD HH:mm:ss"
							:disabledDate="disabledDate"
							v-model:value="searchFormState.salaryTime"
							picker="month"
						/>
					</a-form-item>
				</a-col>
				<!--				<a-col :span="6">-->
				<!--					<a-form-item label="所属部门" name="org">-->
				<!--						<a-input v-model:value="searchFormState.org" placeholder="请输入所属部门" />-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->
				<a-col :span="6">
					<!--					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>-->
					<!--					<a-button style="margin: 0 8px" @click="reset">重置</a-button>-->
				</a-col>
			</a-row>
		</a-form>
		<s-table
			ref="tableRef"
			@resizeColumn="handleResizeColumn"
			:scroll="{ x: 3000, y: 500 }"
			:columns="columns"
			:data="loadData"
			bordered
			:tool-config="toolConfig"
		>
			<template #bodyCell="{ column, text, record }">
				<!--				<template v-if="column.dataIndex === 'totalCommission'">-->
				<!--					{{ calcTotalCommission(editableData[record.key] ? editableData[record.key] : record) }}-->
				<!--				</template>-->
				<!--				<template-->
				<!--					v-if="-->
				<!--						[-->
				<!--							'vacationSubAmount',-->
				<!--							'senioritySalary',-->
				<!--							'performanceSalary',-->
				<!--							'workSalary',-->
				<!--							'basicSalary',-->
				<!--							'baseAmount',-->
				<!--							'transactionVolume',-->
				<!--							'receivedAmount',-->
				<!--							'taxFreight',-->
				<!--							'beforeReceivedAmount',-->
				<!--							'rentSubsidies',-->
				<!--							'mealAllowance',-->
				<!--							'dormitoryRent',-->
				<!--							'monthlyCommission',-->
				<!--							'beforeCommission',-->
				<!--							'totalCommission',-->
				<!--							'meritBonuses',-->
				<!--							'payableAmount',-->
				<!--							'personalIncomeTax',-->
				<!--							'socialSecurity',-->
				<!--							'actualAmount'-->
				<!--						].includes(column.dataIndex)-->
				<!--					"-->
				<!--				>-->
				<!--					<div>-->
				<!--						<a-input-number-->
				<!--							:key="record.id"-->
				<!--							@change="updateFields(column.dataIndex, record)"-->
				<!--							:min="0"-->
				<!--							:precision="2"-->
				<!--							v-if="editableData[record.key]"-->
				<!--							v-model:value="editableData[record.key][column.dataIndex]"-->
				<!--							style="margin: -5px 0"-->
				<!--						/>-->
				<!--						<template v-else>-->
				<!--							{{ text }}-->
				<!--						</template>-->
				<!--					</div>-->
				<!--				</template>-->
				<!--				<template v-if="column.dataIndex === 'baseAmount'">-->
				<!--					{{ calcBaseAmount(editableData[record.key] ? editableData[record.key] : record) }}-->
				<!--				</template>-->

				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<!--						<div class="editable-row-operations" v-if="hasPerm('bizPayrollEdit')">-->
						<!--							<span v-if="editableData[record.key]">-->
						<!--								<a-space>-->
						<!--									<template v-if="!editableData[record.key].loading">-->
						<!--										<a-typography-link @click="save(record.key)">保存</a-typography-link>-->
						<!--										<a-popconfirm title="是否取消?" @confirm="cancel(record.key)">-->
						<!--											<a>取消</a>-->
						<!--										</a-popconfirm>-->
						<!--									</template>-->

						<!--									<a-spin v-else></a-spin>-->
						<!--								</a-space>-->
						<!--							</span>-->
						<!--							<span v-else>-->
						<!--								<a @click="edit(record.key)">编辑</a>-->
						<!--							</span>-->
						<!--						</div>-->

						<!--						<a-divider type="vertical" v-if="hasPerm(['bizPayrollEdit', 'bizPayrollDelete'], 'and')" />-->
						<a-popconfirm title="确定要删除吗？" @confirm="deleteBizPayroll(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('bizPayrollDelete')">删除</a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
			<!--			<template #summary>-->
			<!--				<a-table-summary fixed>-->
			<!--					<a-table-summary-row>-->
			<!--						<a-table-summary-cell :fixed="'left'" :col-span="3">总计</a-table-summary-cell>-->
			<!--						<a-table-summary-cell :key="i" v-for="(item, i) in totalData" :fixed="'left'" :col-span="3">-->
			<!--							{{ item.amount }}-->
			<!--						</a-table-summary-cell>-->
			<!--					</a-table-summary-row>-->
			<!--				</a-table-summary>-->
			<!--			</template>-->
		</s-table>
	</a-card>
</template>

<script setup name="bizpayroll">
	import { cloneDeep } from 'lodash-es'

	import bizPayrollApi from '@/api/biz/bizPayrollApi'
	import dayjs from '@/utils/dayjs'
	import { Decimal } from 'decimal.js'
	import { useLoading } from '@/composables/useLoading'
	import downloadUtil from '@/utils/downloadUtil'
	import { message } from 'ant-design-vue'

	import { useTemplateRef } from 'vue'

	function handleResizeColumn(w, col) {
		col.width = w
	}

	const searchFormState = ref({
		salaryTime: dayjs().subtract(1, 'month').format('YYYY-MM-DD HH:mm:ss')
	})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: false, height: false, columnSetting: false, striped: false }
	const columns = [
		// {
		// 	title: '工资条所属时间',
		// 	dataIndex: 'salaryTime'
		// },

		{
			title: '所属用户',
			dataIndex: 'headName',
			width: 100,
			key: 'name',
			fixed: 'left',
			resizable: true
		},
		{
			title: '所属部门',
			dataIndex: 'orgName',
			fixed: 'left',
			width: 200,
			resizable: true,
			ellipsis: true
		},
		{
			title: '底薪工资',
			dataIndex: 'basicSalary',
			resizable: true
		},

		{
			title: '岗位工资',
			dataIndex: 'postWage',
			resizable: true
		},

		{
			title: '工龄工资',
			dataIndex: 'senioritySalary'
		},
		{
			title: '绩效工资',
			dataIndex: 'performanceSalary'
		},
		{
			title: '加班工资',
			dataIndex: 'workSalary',
			resizable: true
		},

		{
			title: '房租补贴',
			dataIndex: 'rentSubsidies'
		},
		{
			title: '餐补补贴',
			dataIndex: 'mealAllowance'
		},
		{
			title: '宿舍租金',
			dataIndex: 'dormitoryRent',
			resizable: true
		},
		{
			title: '基本工资合计',
			dataIndex: 'baseAmount',
			width: 150,
			resizable: true
		},
		{
			title: '当月成交额',
			dataIndex: 'transactionVolume',
			resizable: true
		},
		{
			title: '当月到账额',
			dataIndex: 'receivedAmount',
			resizable: true
		},
		{
			title: '税运费',
			dataIndex: 'taxFreight',
			resizable: true
		},
		{
			title: '当月提成',
			dataIndex: 'monthlyCommission',
			resizable: true
		},
		{
			title: '以往到账额',
			dataIndex: 'beforeReceivedAmount',
			resizable: true
		},
		{
			title: '以往提成',
			dataIndex: 'beforeCommission',
			resizable: true
		},

		{
			title: '提成总计',
			dataIndex: 'totalCommission',
			resizable: true
		},
		{
			title: '业绩奖金',
			dataIndex: 'meritBonuses',
			resizable: true
		},
		// {
		// 	title: '事假天数',
		// 	dataIndex: 'vacation',
		// 	resizable: true
		// },
		{
			title: '事假扣款',
			dataIndex: 'vacationSubAmount',
			resizable: true
		},
		{
			title: '年终奖',
			dataIndex: 'yearEndBonus',
			resizable: true
		},

		{
			title: '应发金额',
			dataIndex: 'payableAmount',
			resizable: true
		},
		{
			title: '个税',
			dataIndex: 'personalIncomeTax',
			resizable: true
		},
		{
			title: '社保',
			dataIndex: 'socialSecurity',
			resizable: true
		},
		{
			title: '实发金额',
			dataIndex: 'actualAmount',
			resizable: true
		},
		{
			title: '公账',
			dataIndex: 'publicAccount',
			resizable: true
		},
		{
			title: '私账',
			dataIndex: 'privateAccount',
			resizable: true
		},
		{
			title: '备注',
			dataIndex: 'remark',
			resizable: true
		}
	]
	const dataSource = ref([])
	const editableData = reactive({})

	const updateFields = (dataIndex, record) => {
		const key = record.key
		if (
			[
				'senioritySalary',
				'performanceSalary',
				'workSalary',
				'basicSalary',
				'rentSubsidies',
				'mealAllowance',
				'dormitoryRent'
			].includes(dataIndex)
		) {
			editableData[key].baseAmount = calcBaseAmount(editableData[key])
			updateFields('baseAmount', record)
		}
		if (['baseAmount', 'monthlyCommission', 'beforeCommission'].includes(dataIndex)) {
			editableData[key].totalCommission = calcTotalCommission(editableData[key])
			updateFields('totalCommission', record)
		}

		if (['meritBonuses', 'totalCommission', 'baseAmount', 'vacationSubAmount'].includes(dataIndex)) {
			editableData[key].payableAmount = calcPayableAmount(editableData[key])
			updateFields('payableAmount', record)
		}

		if (['payableAmount', 'personalIncomeTax', 'socialSecurity'].includes(dataIndex)) {
			editableData[key].actualAmount = calcActualAmount(editableData[key])
		}
	}

	const calcActualAmount = (record) => {
		const { payableAmount, personalIncomeTax, socialSecurity } = record
		return new Decimal(payableAmount ? payableAmount : 0)
			.sub(new Decimal(personalIncomeTax ? personalIncomeTax : 0))
			.sub(new Decimal(socialSecurity ? socialSecurity : 0))
			.toString()
	}

	const calcTotalCommission = (record) => {
		const { monthlyCommission, beforeCommission } = record

		const totalAdditions = Decimal.sum(
			monthlyCommission ? monthlyCommission : 0,
			beforeCommission ? beforeCommission : 0
		)
		return totalAdditions.toString()
	}

	const calcBaseAmount = (record) => {
		const { basicSalary, senioritySalary, performanceSalary, workSalary, rentSubsidies, mealAllowance, dormitoryRent } =
			record

		// 使用 Decimal.sum 方法来计算总和，并处理 null 值
		const totalAdditions = Decimal.sum(
			basicSalary ? basicSalary : 0,
			senioritySalary ? senioritySalary : 0,
			performanceSalary ? performanceSalary : 0,
			workSalary ? workSalary : 0,
			rentSubsidies ? rentSubsidies : 0,
			mealAllowance ? mealAllowance : 0
		)
		const totalSubtractions = new Decimal(dormitoryRent || 0)

		// 计算最终结果
		const result = totalAdditions.sub(totalSubtractions)

		return result.toString()
	}

	const calcPayableAmount = (record) => {
		const base = new Decimal(calcBaseAmount(record))
		const commission = new Decimal(calcTotalCommission(record))
		const payableAmount = base.add(commission).add(record.meritBonuses).sub(record.vacationSubAmount)
		return payableAmount.toString()
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

		// salaryTime范围查询条件重载
		if (searchFormParam.salaryTime) {
			// 解析日期字符串
			const date = dayjs(searchFormParam.salaryTime)
			// 获取指定月份的开始时间
			const startOfMonth = date.startOf('month').format('YYYY-MM-DD HH:mm:ss')
			// 获取指定月份的结束时间
			const endOfMonth = date.endOf('month').format('YYYY-MM-DD HH:mm:ss')
			searchFormParam.startSalaryTime = startOfMonth
			searchFormParam.endSalaryTime = endOfMonth
			delete searchFormParam.salaryTime
		}
		return bizPayrollApi
			.bizPayrollMyPage(
				Object.assign(parameter, searchFormParam, {
					sortField: 'org'
				})
			)
			.then((data) => {
				data.records = data.records.map((v) => {
					return {
						key: v.id,
						...v,
						loading: false
					}
				})
				dataSource.value = data.records

				return data
			})
	}
	// 重置
	const reset = () => {
		tableRef.value.refresh(true)
	}

	const disabledDate = (date) => {
		const now = dayjs()
		const currentMonthStart = now.startOf('month')
		// 将给定日期转换为 dayjs 对象
		const givenDate = dayjs(date)
		// 判断给定日期是否在本月之前
		return !givenDate.isBefore(currentMonthStart)
	}

	watch(searchFormState.value, () => {
		tableRef.value.refresh(true)
	})
</script>
