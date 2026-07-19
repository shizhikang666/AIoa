<template>
	<a-skeleton active :loading="loading">
		<template v-if="error">
			<a-result status="500" title="500" sub-title="服务器错误">
				<template #extra>
					<a-button type="primary" @click="load">重载</a-button>
				</template>
			</a-result>
		</template>
		<template v-else>
			<a-descriptions bordered title="项目信息" size="small">
				<a-descriptions-item label="项目名称">
					<a-typography-link v-if="canOpenProjectDetail" @click="openProjectDetail">
						{{ projectBaseInfo.projectName }}
					</a-typography-link>
					<span v-else>{{ projectBaseInfo.projectName }}</span>
				</a-descriptions-item>
				<a-descriptions-item label="备注">
					{{ baseInfo.remark }}
				</a-descriptions-item>
				<a-descriptions-item label="变更金额">
					{{ baseInfo.amount }}
				</a-descriptions-item>
				<a-descriptions-item label="退回仓库">
					{{ baseInfo.warehouseName }}
				</a-descriptions-item>
				<a-descriptions-item label="是否退款">
					<a-tag :color="baseInfo.refundRequired ? 'blue' : 'default'">
						{{ baseInfo.refundRequired ? '需要退款' : '无需退款' }}
					</a-tag>
				</a-descriptions-item>
				<a-descriptions-item v-if="baseInfo.refundRequired" label="财务">
					{{ baseInfo.treasurerName || baseInfo.treasurer }}
				</a-descriptions-item>
			</a-descriptions>
			<br />
			<a-typography-title :level="5">退货表单</a-typography-title>
			<br />
			<a-table :pagination="false" size="middle" bordered :data-source="baseInfo.productList" :columns="columns">
				<template #bodyCell="{ column }">
					<template v-if="column.dataIndex === 'warehousesId'"></template>
				</template>
			</a-table>
		</template>
	</a-skeleton>
	<detail ref="projectDetailRef"></detail>
</template>
<script setup name="projectReturnInfo">
	import { useLoading } from '@/composables/useLoading'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import { useTemplateRef } from 'vue'
	import detail from '@/views/biz/saleproject/detail.vue'
	import WarehousesApi from '@/api/biz/warehousesApi'
	import userCenterApi from '@/api/sys/userCenterApi'
	import { safeJsonParse } from '@/utils/json'
	import { canOpenFullSaleProjectDetail } from '@/utils/permission'

	const { id } = defineProps({
		id: {
			required: true,
			type: String
		}
	})
	const projectBaseInfo = ref({})
	const baseInfo = ref({})
	const projectDetailRef = useTemplateRef('projectDetailRef')
	const canOpenProjectDetail = canOpenFullSaleProjectDetail()
	const openProjectDetail = () => {
		if (!projectBaseInfo.value.id) {
			return
		}
		projectDetailRef.value.onOpen({
			id: projectBaseInfo.value.id
		})
	}
	const { loading, load, error } = useLoading(async () => {
		const fields = [
			'projectId',
			'projectName',
			'remark',
			'amount',
			'productList',
			'warehousesId',
			'refundRequired',
			'treasurer'
		]
		const res = await bizProcessApi.bizVariable({ id: id, fields })
		const result = {}
		res.forEach((item) => {
			result[item.name] = item.value
		})
		result.productList = Array.isArray(result.productList) ? result.productList : []
		result.refundRequired = ![false, 0, '0', 'false'].includes(result.refundRequired)
		if (result.treasurer) {
			const users = await userCenterApi.userCenterGetUserListByIdList({ idList: [result.treasurer] }).catch(() => [])
			result.treasurerName = users[0]?.name || ''
		}

		let details = {
			bizSaleProject: {
				id: result.projectId,
				projectName: result.projectName || result.projectId
			}
		}
		if (canOpenProjectDetail && result.projectId) {
			details = await bizSaleProjectApi.bizSaleProjectDetail({ id: result.projectId })
		}

		const list = await WarehousesApi.warehousesList().catch(() => [])
		const find = list.find((v) => {
			return v.id === result.warehousesId
		})

		const objectIds = result.productList.map((v) => v.projectProductItemId).filter(Boolean)
		const productItems = objectIds.length
			? await bizProcessApi.bizProcessProjectProductItemRelationList({ id, objectIds })
			: []
		productItems.forEach((v) => {
			const product = safeJsonParse(v.extJson, {}).product || {}
			const find = result.productList.find((f) => {
				return f.projectProductItemId === v.objectId
			})
			if (find && !find.children) {
				find.children = []
			}
			if (find && find.children) {
				find.children.push({ ...product, amount: v.number })
			}
		})

		projectBaseInfo.value = details.bizSaleProject
		baseInfo.value = result
		baseInfo.value.warehouseName = find ? find.name : ''
	})

	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '20%'
		},
		{
			title: '数量',
			width: '10%',
			dataIndex: 'amount'
		},

		{
			title: '备注',
			width: '25%',
			dataIndex: 'remark'
		}
	]
	load()
</script>
<style scoped></style>
