<template>
	<a-skeleton active :loading="loading">
		<template v-if="!error">
			<a-descriptions bordered title="基本信息" size="small">
				<a-descriptions-item :span="2" label="采购订单编号">
					<a-typography-link @click="bizpurchaseorderDetalRef.onOpen({ id: details.orderId })">
						{{ details.orderId }}
					</a-typography-link>
				</a-descriptions-item>
				<a-descriptions-item :span="2" label="仓库">
					{{ warehouse.name }}
				</a-descriptions-item>

				<a-descriptions-item :span="2" label="物流编号">
					{{ details.logisticsId }}
				</a-descriptions-item>
				<a-descriptions-item :span="10" label="备注">
					{{ details.remark }}
				</a-descriptions-item>
			</a-descriptions>
		</template>

		<a-result v-if="error" status="500" title="500" sub-title="服务器错误">
			<template #extra>
				<a-button type="primary" @click="load">重新加载</a-button>
			</template>
		</a-result>
	</a-skeleton>
	<bizpurchaseorderDetal ref="bizpurchaseorderDetalRef"></bizpurchaseorderDetal>
</template>

<script setup name="procureWarehouseInfo">
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import supplierApi from '@/api/biz/supplierApi'
	import productDetails from '@/views/biz/bizproduct/details/details.vue'
	import warehousesApi from '@/api/biz/warehousesApi'
	import { useTemplateRef } from 'vue'
	import bizpurchaseorderDetal from '@/views/biz/bizpurchaseorder/details/index.vue'

	const bizpurchaseorderDetalRef = useTemplateRef('bizpurchaseorderDetalRef')
	const loading = ref(false)
	const error = ref(false)
	const { id } = defineProps({
		id: {
			type: String,
			required: true
		}
	})

	const warehouse = ref({})

	const details = ref({})
	const load = async () => {
		error.value = false
		loading.value = true
		try {
			const fields = ['orderId', 'warehousesId', 'logisticsId', 'remark']
			const res = await bizProcessApi.bizVariable({ id: id, fields })
			let result = {}
			res.forEach((item) => {
				result[item.name] = item.value
			})

			details.value = result
			warehouse.value = await warehousesApi.warehousesDetail({ id: result.warehousesId })
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	load()
</script>

<style scoped></style>
