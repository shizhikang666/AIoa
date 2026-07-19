<template>
	<a-skeleton :loading="loading" active>
		<error-result @reload="load" v-if="error"></error-result>
		<template v-else>
			<a-descriptions :labelStyle="{ minWidth: '100px' }" :column="2" bordered title="基本信息" size="small">
				<a-descriptions-item label="考勤分类">
					{{ $TOOL.dictTypeDataByPathReturnEmpty('vacation', 'leave', info.category) }}
					{{ $TOOL.dictTypeDataByPathReturnEmpty('vacation', 'GoOut', info.category) }}
				</a-descriptions-item>

				<a-descriptions-item label="天数">
					{{ info.amount }}
				</a-descriptions-item>
				<a-descriptions-item label="开始时间">
					{{ info.startTime }}
				</a-descriptions-item>
				<a-descriptions-item label="结束时间">
					{{ info.endTime }}
				</a-descriptions-item>
				<a-descriptions-item v-if="info.objectId" :span="6" label="单号">
					<a-typography-link v-if="canOpenProjectDetail" @click="open(info.objectId)">
						{{ info.objectId }}
					</a-typography-link>
					<span v-else>{{ info.objectId }}</span>
				</a-descriptions-item>
				<a-descriptions-item :span="3" label="备注">
					{{ info.remark }}
				</a-descriptions-item>
			</a-descriptions>
			<Detail ref="detailRef"></Detail>

			<!--			<br />-->
			<!--			<a-descriptions bordered title="结算信息" v-if="isUseAccount" size="small">-->
			<!--				<a-descriptions-item label="结算账户">-->
			<!--					{{ account.accountName }}-->
			<!--				</a-descriptions-item>-->
			<!--				<a-descriptions-item label="结算时间">-->
			<!--					{{ info.payerTime }}-->
			<!--				</a-descriptions-item>-->
			<!--			</a-descriptions>-->
		</template>
	</a-skeleton>
</template>
<script setup lang="js" name="">
	import { useLoading } from '@/composables/useLoading'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import Detail from '@/views/biz/saleproject/detail.vue'
	import { canOpenFullSaleProjectDetail } from '@/utils/permission'

	const { id } = defineProps({
		id: {
			type: String,
			required: true
		}
	})
	const info = ref({})

	const detailRef = ref()
	const canOpenProjectDetail = canOpenFullSaleProjectDetail()
	const open = (id) => {
		if (!canOpenProjectDetail || !id) {
			return
		}
		detailRef.value.onOpen({ id })
	}
	const { loading, load, error } = useLoading(async () => {
		const fields = ['startTime', 'endTime', 'remark', 'amount', 'category', 'initiator', 'objectId']
		const res = await bizProcessApi.bizVariable({ id: id, fields })
		const result = {}
		res.forEach((item) => {
			result[item.name] = item.value
		})
		info.value = result
	})
	load()
</script>

<style scoped></style>
