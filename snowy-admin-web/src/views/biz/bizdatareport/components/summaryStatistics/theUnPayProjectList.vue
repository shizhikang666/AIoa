<template>
	<xn-form-container :title="title" :width="1200" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-table size="small" bordered :pagination="false" :data-source="data" :columns="columns">
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex == 'projectName'">
					<a-badge dot :count="record.processIdList ? record.processIdList.length : 0">
						<a-typography-link @click="detailRef.onOpen(record)">{{ record.projectName }} </a-typography-link>
					</a-badge>
				</template>
			</template>
			<template #footer>
				<a-row justify="end">
					共计：
					<a-typography-text style="padding-right: 6px" strong>￥{{ totalMonthUnAmount }}</a-typography-text>
				</a-row>
			</template>
		</a-table>
	</xn-form-container>
	<Detail ref="detailRef"></Detail>
</template>
<script setup name="theUnPayProjectList" lang="js">
	import { Decimal } from 'decimal.js'
	import dayjs from '@/utils/dayjs'
	import Detail from '@/views/biz/saleproject/detail.vue'
	import { useTemplateRef } from 'vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'

	const detailRef = useTemplateRef('detailRef')
	const title = ref('')
	const open = ref(false)
	const data = ref([])

	const totalMonthUnAmount = computed(() => {
		return data.value.reduce((acc, cur) => {
			return acc.add(cur.monthUnAmount)
		}, new Decimal(0))
	})
	const columns = ref([
		{
			title: '项目名称',
			dataIndex: 'projectName'
		},
		{
			title: '业务员',
			dataIndex: 'headName'
		},
		{
			title: '客户名称',
			dataIndex: 'customerName'
		},
		{
			title: '总金额',
			dataIndex: 'totalPrice',
			key: 'totalPrice',
			sorter: {
				compare: (a, b) => a.totalPrice - b.totalPrice,
				multiple: 1
			}
		},
		{
			title: '当月收款金额',
			dataIndex: 'monthAmount',
			key: 'monthAmount',
			sorter: {
				compare: (a, b) => a.monthAmount - b.monthAmount,
				multiple: 1
			}
		},
		{
			title: '当月新增未回款金额',
			dataIndex: 'monthUnAmount',
			key: 'monthUnAmount',
			sorter: {
				compare: (a, b) => a.monthUnAmount - b.monthUnAmount,
				multiple: 2
			}
		}
	])
	const onClose = () => {
		open.value = false
	}
	const onOpen = async (record) => {
		console.log(record)
		title.value = `${dayjs(record.time).month() + 1} 月新增未回款详情`
		const filterProject = record.projectList
			.filter((v) => {
				const amount = v.paymentRecords.reduce((pre, current) => {
					return pre.add(current.amount)
				}, new Decimal(0))
				const historyAmount = v.project.historyAmount ? v.project.historyAmount : 0

				return (
					new Decimal(v.project.totalPrice).sub(new Decimal(historyAmount)).sub(amount).comparedTo(new Decimal(0)) > 0
				)
			})
			.map((v) => {
				const historyAmount = v.project.historyAmount ? v.project.historyAmount : 0
				const amount = v.paymentRecords.reduce((pre, current) => {
					return pre.add(current.amount)
				}, new Decimal(0))
				return {
					...v.project,
					monthAmount: amount.add(new Decimal(historyAmount)).toString(),
					monthUnAmount: new Decimal(v.project.totalPrice)
						.sub(new Decimal(historyAmount))
						.sub(new Decimal(amount))
						.toString()
				}
			})

		const processInfo = await bizProcessApi.bizProcessQuery({
			processCategory: 'Process_sale_project_play',
			variableName: 'projectId',
			findValue: 'amount',
			variable: filterProject
				.map((value, index) => {
					return value.id
				})
				.join(',')
		})

		const processMap = {}
		const amountMap = {}
		processInfo.forEach((item) => {
			processMap[item.variable] = item.processIdList
			amountMap[item.variable] = Object.keys(item.variableMap)
				.reduce((pre, key) => {
					return pre.add(new Decimal(item.variableMap[key].amount))
				}, new Decimal(0))
				.toNumber()
		})

		filterProject.forEach((v) => {
			v.processIdList = processMap[v.id]
			v.auditAmount = amountMap[v.id]
		})

		data.value = filterProject
		open.value = true
	}

	defineExpose({
		onOpen
	})
</script>

<style scoped></style>
