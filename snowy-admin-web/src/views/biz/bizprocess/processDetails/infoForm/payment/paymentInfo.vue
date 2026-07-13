<template>
	<a-skeleton v-if="!error" active :loading="loading">
		<a-descriptions bordered title="项目信息" size="small">
			<a-descriptions-item label="结算账户">
				{{ account.accountName }}
			</a-descriptions-item>

			<a-descriptions-item label="收款类型">
				{{
					$TOOL.dictTypeDataByPath(
						'SETTLEMENT_ACCOUNT',
						'SETTLEMENT_CATEGORY',
						'INCOME_CATEGORY',
						...baseInfo.settlementCategory.split('/')
					)
				}}
			</a-descriptions-item>

			<a-descriptions-item label="收款时间">
				{{ baseInfo.payerTime }}
			</a-descriptions-item>

			<a-descriptions-item label="金额">
				{{ baseInfo.amount }}
			</a-descriptions-item>
			<a-descriptions-item :span="3" label="备注">
				{{ baseInfo.remark }}
			</a-descriptions-item>
		</a-descriptions>
	</a-skeleton>
</template>

<script setup lang="js" name="PaymentInfo">
	import bizProcessApi from '@/api/biz/bizProcessApi'

	const props = defineProps({
		id: {
			type: String,
			required: true
		}
	})
	const projectBaseInfo = ref({})
	const baseInfo = ref({})

	const loading = ref(false)
	const error = ref(false)
	const account = ref({})

	const load = async () => {
		error.value = false
		loading.value = true
		try {
			const fields = ['remark', 'amount', 'accountId', 'accountName', 'payer', 'payerTime', 'settlementCategory']
			const res = await bizProcessApi.bizVariable({ id: props.id, fields })
			const result = {}
			res.forEach((item) => {
				result[item.name] = item.value
			})
			baseInfo.value = result
			account.value = {}
			if (result.accountId) {
				account.value = {
					id: result.accountId,
					accountName: result.accountName || result.accountId
				}
			}
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	load()
</script>

<style scoped></style>
