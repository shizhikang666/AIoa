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
<script setup name="theTotalUnPaidAmount" lang="js">
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
			return acc.add(cur.unPayAmount)
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
			title: '未收款',
			dataIndex: 'unPayAmount'
		}
	])
	const onClose = () => {
		open.value = false
	}
	const onOpen = async (record) => {
		title.value = `${dayjs(record.time).month() + 1} 月未回款详情`
		data.value = record.unPaidProjectDetail.map((v) => {
			return {
				...v.project,
				unPayAmount: v.amount
			}
		})
		open.value = true
	}

	defineExpose({
		onOpen
	})
</script>

<style scoped></style>
