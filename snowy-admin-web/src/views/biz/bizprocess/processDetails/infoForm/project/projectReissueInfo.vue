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
			</a-descriptions>
			<br />
			<a-typography-title :level="5">补货表单</a-typography-title>
			<br />
			<a-table :pagination="false" size="middle" bordered :data-source="baseInfo.productList" :columns="columns">
				<template #bodyCell="{ column, text, record, index }">
					<template v-if="column.dataIndex === 'warehousesId'"> </template>
				</template>
			</a-table>
		</template>
	</a-skeleton>
	<detail ref="projectDetailRef"></detail>
</template>
<script setup>
	import { useLoading } from '@/composables/useLoading'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import { useTemplateRef } from 'vue'
	import { canOpenFullSaleProjectDetail } from '@/utils/permission'

	import detail from '@/views/biz/saleproject/detail.vue'

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
		projectDetailRef.value.onOpen({
			id: projectBaseInfo.value.id
		})
	}
	const { loading, load, error } = useLoading(async () => {
		const fields = ['projectId', 'projectName', 'remark', 'amount', 'productList']
		const res = await bizProcessApi.bizVariable({ id: id, fields })
		const result = {}
		res.forEach((item) => {
			result[item.name] = item.value
		})
		projectBaseInfo.value = {
			id: result.projectId,
			projectName: result.projectName || result.projectId
		}
		if (canOpenProjectDetail && result.projectId) {
			const details = await bizSaleProjectApi.bizSaleProjectDetail({ id: result.projectId })
			projectBaseInfo.value = details.bizSaleProject
		}
		baseInfo.value = result
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
			dataIndex: 'number'
		},
		{
			title: '单价',

			dataIndex: 'unitPrice'
		},
		{
			title: '优惠率',

			dataIndex: 'discountRate'
		},
		{
			title: '售价',

			dataIndex: 'price'
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
