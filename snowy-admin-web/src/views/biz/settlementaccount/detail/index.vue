<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="结算账户详细信息"
		:width="'70%'"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="loading">
			<a-tabs :destroyInactiveTabPane="true" v-if="!error" v-model:active-key="activeComponents">
				<a-tab-pane key="baseInfo" tab="基本信息">
					<a-descriptions bordered title="账户信息" size="small">
						<a-descriptions-item label="账户名称">{{ account.accountName }}</a-descriptions-item>
						<a-descriptions-item label="账户编号">{{ account.accountNumber }}</a-descriptions-item>
						<a-descriptions-item label="初始资金">
							<a-typography-text style="padding-right: 6px" strong>￥ {{ account.initialAmount }} </a-typography-text>
						</a-descriptions-item>
						<a-descriptions-item label="当前金额">
							<a-typography-text style="padding-right: 6px" strong>￥ {{ account.currentAmount }} </a-typography-text>
						</a-descriptions-item>
						<a-descriptions-item label="账户状态">{{ account.accountStatus }}</a-descriptions-item>
						<a-descriptions-item label="创建用户">{{ account.createUserName }}</a-descriptions-item>
						<a-descriptions-item label="创建时间">{{ account.createTime }}</a-descriptions-item>
						<a-descriptions-item label="最近更改时间">{{ account.updateTime }}</a-descriptions-item>
					</a-descriptions>

					<br />
					<a-descriptions v-if="account.archiveAmount" bordered title="归档信息" size="small">
						<a-descriptions-item label="归档余额">{{ account.archiveAmount }}</a-descriptions-item>
						<a-descriptions-item label="归档日期">{{ account.archiveTime }}</a-descriptions-item>
					</a-descriptions>
					<br />
					<a-descriptions :column="2" bordered title="近三月余额信息" size="small">
						<template :key="item.date" v-for="(item, i) in monthAmountResult">
							<a-descriptions-item label="日期">{{ item.date }}</a-descriptions-item>
							<a-descriptions-item label="余额">{{ item.amount }}</a-descriptions-item>
						</template>
					</a-descriptions>
				</a-tab-pane>
				<a-tab-pane key="payment" tab="收入记录">
					<paymentRecord :account-id="account.id"></paymentRecord>
				</a-tab-pane>
				<a-tab-pane key="expenditure" tab="支出记录">
					<expenditureRecord :account-id="account.id"></expenditureRecord>
				</a-tab-pane>
				<a-tab-pane key="SettlementAccountStatement" tab="账户流水">
					<settlementAccountStatementRecord :account-id="account.id"></settlementAccountStatementRecord>
				</a-tab-pane>
			</a-tabs>
			<div v-else>
				<a-space style="width: 100%" direction="vertical" align="center">
					<a-result status="500" title="500" sub-title="服务器错误">
						<template #extra>
							<a-button type="primary" @click="onClose">关闭</a-button>
						</template>
					</a-result>
				</a-space>
			</div>
		</a-skeleton>
	</xn-form-container>
</template>
<script lang="js" setup name="settlementAccountDetail">
	import { useLoading } from '@/composables/useLoading'
	import settlementAccountApi from '@/api/biz/settlementAccountApi'
	import expenditureRecord from './tabs/expenditureRecord/index.vue'
	import paymentRecord from './tabs/paymentRecord/index.vue'
	import settlementAccountStatementRecord from './tabs/settlementAccountStatementRecord/index.vue'
	import bizPaymentRecordApi from '@/api/biz/bizPaymentRecordApi'
	import bizExpenditureRecordApi from '@/api/biz/bizExpenditureRecordApi'
	import dayjs from '@/utils/dayjs'
	import { Decimal } from 'decimal.js'

	const activeComponents = ref('baseInfo')
	const open = ref(false)
	const onClose = () => {
		open.value = false
	}

	const account = ref({})

	const monthAmountResult = ref([])

	const { load, loading, error } = useLoading(async (id) => {
		account.value = await settlementAccountApi.settlementAccountDetail({ id: id })
		const settlement = account.value

		const createTime = dayjs(settlement.createTime).subtract(1, 'month').format('YYYY-MM-DD HH:mm:ss')

		const queryParam = {
			targetId: id,
			payerStartTime: settlement.archiveTime ? settlement.archiveTime : createTime,
			payerEndTime: dayjs().format('YYYY-MM-DD HH:mm:ss')
		}

		let amount = new Decimal(settlement.archiveTime ? settlement.archiveAmount : settlement.initialAmount)
		const bizPaymentRecordList = await bizPaymentRecordApi.bizPaymentRecordList(queryParam)
		const bizExpenditureRecordList = await bizExpenditureRecordApi.bizExpenditureRecordList(queryParam)
		const recordMap = new Map()
		bizPaymentRecordList.forEach((item) => {
			const key = dayjs(item.payerTime).format('YYYY-MM')
			if (!recordMap.has(key)) {
				recordMap.set(key, {
					paymentList: [],
					expenditureList: []
				})
			}
			recordMap.get(key).paymentList.push(item)
		})
		bizExpenditureRecordList.forEach((item) => {
			const key = dayjs(item.payerTime).format('YYYY-MM')
			if (!recordMap.has(key)) {
				recordMap.set(key, {
					paymentList: [],
					expenditureList: []
				})
			}
			recordMap.get(key).expenditureList.push(item)
		})
		// 提取键并转换为 Date 对象
		const keys = Array.from(recordMap.keys())
			.map((key) => new Date(key))
			.sort((a, b) => a - b)
			.map((v) => {
				return dayjs(v).format('YYYY-MM')
			})

		monthAmountResult.value = keys.map((key) => {
			const payment = recordMap.get(key).paymentList.reduce((acc, item) => {
				return new Decimal(item.amount).add(acc)
			}, new Decimal(0))

			const expenditure = recordMap.get(key).expenditureList.reduce((acc, item) => {
				return new Decimal(item.amount).add(acc)
			}, new Decimal(0))

			amount = amount.add(payment).sub(expenditure)
			return {
				date: key,
				amount: amount.toString()
			}
		})
	})
	const onOpen = (record) => {
		open.value = true
		activeComponents.value = 'baseInfo'

		load(record.id)
	}

	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
