<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="一键入库"
		:width="800"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="loading">
			<a-tabs v-model:activeKey="activeKey">
				<a-tab-pane tab="基本信息" key="baseInfo">
					<a-form
						:labelCol="{ span: 3 }"
						labelAlign="left"
						ref="formRef"
						:model="formData"
						:rules="formRules"
						layout="horizontal"
					>
						<a-form-item label="入库仓库：" name="warehousesId">
							<a-row :gutter="16">
								<a-col>
									<a-select
										show-search
										:filterOption="filterOption"
										placeholder="请选择入库仓库"
										v-model:value="formData.warehousesId"
										:options="warehousesList"
									></a-select>
								</a-col>
								<a-col>
									<a-popover trigger="click">
										<template #content> 一键入库是将所有自动录入仓库库存</template>

										<a-button type="link" shape="circle">
											<template #icon>
												<QuestionCircleOutlined />
											</template>
										</a-button>
									</a-popover>
								</a-col>
							</a-row>
						</a-form-item>

						<a-form-item label="备注：" name="remark">
							<a-textarea
								v-model:value="formData.remark"
								placeholder="请输入备注"
								:auto-size="{ minRows: 5, maxRows: 5 }"
							/>
						</a-form-item>
					</a-form>
				</a-tab-pane>
			</a-tabs>
		</a-skeleton>
		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="sendLoading">确认</a-button>
		</template>
	</xn-form-container>
</template>
<script setup name="procureInWarehouseForm">
	import { useLoading } from '@/composables/useLoading'
	import { required } from '@/utils/formRules'
	import { rules } from '@/utils/formRules'
	import { useSelectFilterOption } from '@/composables/useSelectFilterOption'
	import { createVNode, ref, useTemplateRef } from 'vue'
	import WarehousesApi from '@/api/biz/warehousesApi'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'

	const emit = defineEmits({ successful: null })
	const visible = ref(false)
	const activeKey = ref('baseInfo')
	const formRef = useTemplateRef('formRef')
	const formData = ref({})
	const filterOption = useSelectFilterOption()
	const formRules = {
		warehousesId: [rules.bankAccount, required('请选择仓库')],
		id: [required('请输入采购单')]
	}
	const warehousesList = ref([])
	const list = ref([])
	const onClose = () => {
		list.value = []
		visible.value = false
	}

	const {
		load: onOpen,
		loading,
		error
	} = useLoading(async (param) => {
		const list = await WarehousesApi.warehousesList()

		warehousesList.value = list.map((v) => {
			return {
				label: v.name,
				value: v.id
			}
		})
		const warehousesId = formData.value.warehousesId

		formData.value = {
			warehousesId: warehousesId ? warehousesId : list.length ? list[0].id : '',
			orderId: param.id,
			remark: ''
		}
		visible.value = true
	})

	const { load: onSubmit, loading: sendLoading } = useLoading(async () => {
		await formRef.value.validate()
		await bizPurchaseOrderApi.bizPurchaseInOneWarehouse({ ...formData.value })
		onClose()
		emit('successful')
	})

	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
