<template>
	<a-skeleton v-if="!error" active :loading="loading">
		<a-descriptions :column="2" :labelStyle="{ minWidth: '100px' }" bordered title="项目信息" size="small">
			<a-descriptions-item label="项目名称">
				<a-typography-link
					@click="
						projectDetail.onOpen({
							id: projectBaseInfo.id
						})
					"
				>
					{{ projectBaseInfo.projectName }}
				</a-typography-link>
			</a-descriptions-item>
			<a-descriptions-item label="结算账户">
				{{ account.accountName }}
			</a-descriptions-item>
			<!--			<a-descriptions-item label="付款人">-->
			<!--				{{baseInfo.payer}}-->
			<!--			</a-descriptions-item>-->
			<a-descriptions-item label="付款时间">
				{{ baseInfo.payerTime }}
			</a-descriptions-item>

			<a-descriptions-item label="打款金额">
				{{ baseInfo.amount }}
			</a-descriptions-item>
			<a-descriptions-item :span="3" label="备注">
				{{ baseInfo.remark }}
			</a-descriptions-item>
		</a-descriptions>
	</a-skeleton>
	<detail ref="projectDetail"></detail>
</template>

<script setup lang="js" name="projectPaymentInfo">
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import settlementAccountApi from '@/api/biz/settlementAccountApi'

	import detail from '@/views/biz/saleproject/detail.vue'

	const projectDetail = ref()

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
			const fields = ['projectId', 'remark', 'amount', 'accountId', 'payer', 'payerTime', 'settlementCategory']
			const res = await bizProcessApi.bizVariable({ id: props.id, fields })
			const result = {}
			res.forEach((item) => {
				result[item.name] = item.value
			})
			const details = await bizSaleProjectApi.bizSaleProjectDetail({ id: result.projectId })
			projectBaseInfo.value = details.bizSaleProject
			baseInfo.value = result

			const accountDetails = await settlementAccountApi.settlementAccountDetail({
				id: result.accountId
			})
			account.value = accountDetails
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	load()
</script>

<style scoped></style>
