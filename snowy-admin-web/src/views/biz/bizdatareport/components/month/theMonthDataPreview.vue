<template>
	<a-card :hoverable="hoverable">
		<a-spin :spinning="loading">
			<reportError v-if="error" @reload="load"></reportError>
			<a-statistic v-else-if="contrast === false" :precision="2" :value="result" style="margin-right: 50px">
				<template #title>
					<span>{{ title }}</span>
					<a-tooltip v-if="info" placement="right">
						<template #title>
							<span>{{ info }}</span>
						</template>
						<question-circle-two-tone style="margin-left: 5px" />
					</a-tooltip>
				</template>

				<template #prefix>
					<slot name="prefix"></slot>
				</template>
			</a-statistic>
			<a-statistic
				v-else-if="contrast === true"
				:precision="2"
				:value="result"
				:value-style="result > preResult ? { color: '#cf1322' } : { color: '#3f8600' }"
				style="margin-right: 50px"
			>
				<template #title>
					<span>{{ title }}</span>
					<a-tooltip v-if="info" placement="right">
						<template #title>
							<span>{{ info }}</span>
						</template>
						<question-circle-two-tone style="margin-left: 5px" />
					</a-tooltip>
				</template>
				<template #prefix>
					<arrow-up-outlined v-if="result > preResult" />
					<arrow-down-outlined v-else />
				</template>
			</a-statistic>
		</a-spin>
	</a-card>
</template>

<script setup name="theMonthDataPreview" lang="js">
	import { useLoading } from '@/composables/useLoading'
	import reportError from '../error/index.vue'
	import dayjs from '@/utils/dayjs'

	// eslint-disable-next-line vue/no-setup-props-destructure
	const { loadData, title, contrast, orgId } = defineProps({
		hoverable: {},
		info: {
			type: String
		},

		title: {
			type: String
		},
		loadData: {
			type: [Function, Promise]
		},
		contrast: {
			type: Boolean,
			default: false
		},
		orgId: {
			type: String
		}
	})
	const preData = () => {
		const now = dayjs()
		// 获取上个月的第一天
		const firstDayOfLastMonth = now.subtract(1, 'month').startOf('month')
		// 获取上个月的最后一天
		const lastDayOfLastMonth = now.subtract(1, 'month').endOf('month')
		// 设置时间为 00:00:00
		const startOfLastMonth = firstDayOfLastMonth.hour(0).minute(0).second(0)
		// 设置时间为 23:59:59
		const endOfLastMonth = lastDayOfLastMonth.hour(23).minute(59).second(59)
		const startCreateTime = startOfLastMonth.format('YYYY-MM-DD HH:mm:ss')
		const endCreateTime = endOfLastMonth.format('YYYY-MM-DD HH:mm:ss')

		return {
			startCreateTime,
			endCreateTime
		}
	}
	const preResult = ref(0)
	const compare = async () => {
		const res = await loadData({
			...preData()
		})

		preResult.value = res.amount
	}
	const result = ref(0)
	const { load, error, loading } = useLoading(async () => {
		// 获取当前日期
		const now = dayjs()
		// 获取本月的第一天
		const firstDayOfMonth = now.startOf('month')
		// 获取本月的最后一天
		const lastDayOfMonth = now.endOf('month')
		// 设置时间为 00:00:00
		const startOfMonth = firstDayOfMonth.hour(0).minute(0).second(0)

		// 设置时间为 23:59:59
		const endOfMonth = lastDayOfMonth.hour(23).minute(59).second(59)

		const startCreateTime = startOfMonth.format('YYYY-MM-DD HH:mm:ss')
		const endCreateTime = endOfMonth.format('YYYY-MM-DD HH:mm:ss')

		const res = await loadData({
			startCreateTime,
			endCreateTime,
			orgId
		})

		result.value = res.amount

		if (contrast) {
			await compare()
		}
	})

	watchEffect(async () => {
		await load()
	})
</script>

<style scoped></style>
