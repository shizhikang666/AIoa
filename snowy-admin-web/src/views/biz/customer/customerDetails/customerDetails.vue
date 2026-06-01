<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="客户详细信息"
		:width="'70%'"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<template v-if="!error" direction="vertical" style="width: 100%" :size="16">
			<!--占位loading-->
			<br v-if="loading" />
			<a-skeleton active :loading="loading">
				<a-tabs v-model:active-key="activeComponents">
					<a-tab-pane key="baseInfo" tab="基本信息">
						<a-descriptions bordered :title="baseInfo.name" size="small">
							<a-descriptions-item label="客户单位名称">{{ baseInfo.name }}</a-descriptions-item>
							<a-descriptions-item label="创建时间">{{ baseInfo.createTime }}</a-descriptions-item>
							<a-descriptions-item label="创建人">{{ baseInfo.createUserName }}</a-descriptions-item>
							<a-descriptions-item label="联系人">{{ baseInfo.contacts }}</a-descriptions-item>
							<a-descriptions-item label="联系电话">{{ baseInfo.phone }}</a-descriptions-item>
							<a-descriptions-item label="地址">{{ baseInfo.address }}</a-descriptions-item>
							<a-descriptions-item label="详细地址">{{ baseInfo.detailsAddress }}</a-descriptions-item>
							<a-descriptions-item label="客户来源">
								{{ $TOOL.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_SOURCE', baseInfo.sourceType) }}
							</a-descriptions-item>
							<a-descriptions-item label="客户类型">
								{{ $TOOL.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_TYPE', baseInfo.customType) }}
							</a-descriptions-item>
							<a-descriptions-item label="负责人">{{ baseInfo.headName }}</a-descriptions-item>
							<a-descriptions-item label="所属部门组织">{{ baseInfo.orgName }}</a-descriptions-item>
							<a-descriptions-item label="备注">{{ baseInfo.remark }}</a-descriptions-item>
							<a-descriptions-item :span="8" label="营业执照" v-if="baseInfo.downloadPath">
								<a-image :width="200" :src="replaceUrlDomain(baseInfo.downloadPath)" />
							</a-descriptions-item>
						</a-descriptions>
					</a-tab-pane>

					<a-tab-pane key="project" tab="项目记录">
						<saleProject :address="baseInfo.address" :customer-name="baseInfo.name" :customer-id="baseInfo.id" />
					</a-tab-pane>
					<a-tab-pane key="followUpRecords" tab="客户跟进记录">
						<followup :customer-id="baseInfo.id"></followup>
					</a-tab-pane>
				</a-tabs>
			</a-skeleton>
		</template>
		<div v-else>
			<a-space style="width: 100%" direction="vertical" align="center">
				<a-result status="500" title="500" sub-title="服务器错误">
					<template #extra>
						<a-button type="primary" @click="onClose">关闭</a-button>
					</template>
				</a-result>
			</a-space>
		</div>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
		</template>
	</xn-form-container>
</template>
<script setup name="customerDetails">
	import customerApi from '@/api/biz/customerApi'
	import followup from './customerDetailsTab/followup/index.vue'
	import saleProject from './customerDetailsTab/saleProject/index.vue'

	const open = ref(false)
	const loading = ref(false)
	const error = ref(false)
	const baseInfo = ref({})
	const activeComponents = ref('baseInfo')
	const onOpen = async (record) => {
		open.value = true
		loading.value = true

		try {
			baseInfo.value = await customerApi.customerDetail({ id: record.id })
		} catch (e) {
			error.value = true
			throw e
		} finally {
			loading.value = false
		}
	}
	// 示例用法

	//兼容旧的
	let oldDomain = 'http://47.95.5.233:7971'
	let newDomain = 'https://oa.zhixinxinli888.com/api/backend'
	const replaceUrlDomain = (originalUrl) => {
		return originalUrl.replace(oldDomain, newDomain)
	}

	const onClose = () => {
		open.value = false
		activeComponents.value = 'baseInfo'
	}
	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
