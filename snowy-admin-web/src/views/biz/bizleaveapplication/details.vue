<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="出差单详情"
		:width="800"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="loading">
			<a-result v-if="error" status="500" title="500" sub-title="Sorry, the server is wrong.">
				<template #extra>
					<a-button type="primary" @click="loadData">重新加载</a-button>
				</template>
			</a-result>
			<template v-else>
				<a-tabs v-model="activeKey">
					<a-tab-pane key="baseInfo" tab="基本信息">
						<a-descriptions bordered title="" size="small">
							<a-descriptions-item :span="6" label="出差日期"
								>{{ details.startTime }} 到 {{ details.endTime }}</a-descriptions-item
							>
							<a-descriptions-item v-if="details.objectId" :span="10" label="项目编号">
								<a-typography-link @click="openProjectDetail">
									{{ details.objectId }}
								</a-typography-link>
							</a-descriptions-item>
							<a-descriptions-item :span="10" label="总天数出差">
								{{ details.amount }}
							</a-descriptions-item>

							<a-descriptions-item :span="10" label="备注">
								{{ details.remark }}
							</a-descriptions-item>
						</a-descriptions>
						<br />
					</a-tab-pane>
				</a-tabs>
			</template>
		</a-skeleton>
		<projectDetails ref="projectDetailsRef" />
	</xn-form-container>
</template>
<script setup name="bizPurchaseOrderDetails">
	import bizLeaveApplicationApi from '@/api/biz/bizLeaveApplicationApi'
	import projectDetails from '@/views/biz/saleproject/detail.vue'
	import { useTemplateRef } from 'vue'

	const productDetailsRef = useTemplateRef('productDetailsRef')
	const visible = ref(false)
	const loading = ref(false)
	const error = ref(false)
	const id = ref('')
	const supplier = ref({})
	const details = ref({})
	const activeKey = ref('baseInfo')
	const loadData = async () => {
		loading.value = true
		error.value = false
		try {
			details.value = await bizLeaveApplicationApi.bizLeaveApplicationDetail({ id: id.value })
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}
	const projectDetailsRef = ref()
	const openProjectDetail = () => {
		projectDetailsRef.value.onOpen({ id: details.value.objectId })
	}

	const onOpen = async (record) => {
		visible.value = true
		id.value = record.id
		await loadData()
	}

	const onClose = () => {
		visible.value = false
	}

	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
