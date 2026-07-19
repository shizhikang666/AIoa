<template>
	<a-skeleton v-if="!error" active :loading="loading">
		<a-descriptions bordered title="发货信息" size="small">
			<a-descriptions-item label="项目名称">
				<a-typography-link
					v-if="canOpenProjectDetail"
					@click="
						projectDetail.onOpen({
							id: projectBaseInfo.id
						})
					"
				>
					{{ projectBaseInfo.projectName }}
				</a-typography-link>
				<span v-else>{{ projectBaseInfo.projectName }}</span>
			</a-descriptions-item>
			<a-descriptions-item label="收货人">
				{{ baseInfo.consignee }}
			</a-descriptions-item>
			<a-descriptions-item label="收货单位">
				{{ baseInfo.unit }}
			</a-descriptions-item>
			<a-descriptions-item label="收货地址">
				{{ baseInfo.address }}
			</a-descriptions-item>
			<a-descriptions-item label="联系电话">
				{{ baseInfo.phone }}
			</a-descriptions-item>
			<a-descriptions-item label="物流类型">
				{{ $TOOL.dictTypeDataByPath('LOGISTICS_CATEGORY', baseInfo.logisticsCategory) }}
			</a-descriptions-item>
			<a-descriptions-item label="物流编号">
				{{ baseInfo.logisticsId }}
			</a-descriptions-item>
			<a-descriptions-item label="运费支付方式">
				{{ $TOOL.dictTypeDataByPath('FREIGHT_CATEGORY', baseInfo.freightCategory) }}
			</a-descriptions-item>
			<a-descriptions-item label="发货日期">
				{{ baseInfo.freightTime }}
			</a-descriptions-item>
			<a-descriptions-item label="运费金额">
				{{ baseInfo.freight }}
			</a-descriptions-item>
			<a-descriptions-item label="备注">
				{{ baseInfo.remark }}
			</a-descriptions-item>
		</a-descriptions>
		<br />
		<a-typography-title :level="5">详细表单</a-typography-title>
		<br />
		<a-table
			:pagination="false"
			size="middle"
			bordered
			:data-source="baseInfo.projectProductItemList"
			:columns="columns"
			row-key="projectProductItemId"
		>
			<template #bodyCell="{ column, text, record, index }">
				<template v-if="column.dataIndex === 'warehousesId'"></template>
			</template>
		</a-table>
	</a-skeleton>
	<detail ref="projectDetail"></detail>
</template>

<script setup lang="js">
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import warehousesApi from '@/api/biz/warehousesApi'
	import detail from '@/views/biz/saleproject/detail.vue'
	import { safeJsonParse } from '@/utils/json'
	import { canOpenFullSaleProjectDetail } from '@/utils/permission'

	const projectDetail = ref()
	const canOpenProjectDetail = canOpenFullSaleProjectDetail()
	const props = defineProps({
		id: {
			type: String,
			required: true
		}
	})

	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '20%'
		},
		{
			title: '出库数量',
			width: '10%',
			dataIndex: 'amount'
		},
		{
			title: '出库仓库',
			width: '25%',
			dataIndex: 'warehouseName'
		},
		{
			title: '备注',
			dataIndex: 'remark'
		}
	]

	const projectBaseInfo = ref({})
	const loading = ref(false)
	const error = ref(false)
	const baseInfo = ref({
		projectProductItemList: []
	})
	const load = async () => {
		error.value = false
		loading.value = true
		try {
			const fields = [
				'projectId',
				'projectName',
				'remark',
				'projectProductItemList',
				'address',
				'unit',
				'freightTime',
				'freightCategory',
				'freight',
				'logisticsId',
				'phone',
				'logisticsCategory',
				'consignee'
			]
			const res = await bizProcessApi.bizVariable({ id: props.id, fields })
			const warehouseList = await warehousesApi.warehousesList()
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
			baseInfo.value.projectProductItemList = Array.isArray(baseInfo.value.projectProductItemList)
				? baseInfo.value.projectProductItemList
				: []

			baseInfo.value.projectProductItemList.forEach((v) => {
				const find = warehouseList.find((warehouse) => {
					return warehouse.id === v.warehousesId
				})
				v.warehouseName = find ? find.name : '未找到仓库信息'
			})

			const map = new Map()
			const objectIds = baseInfo.value.projectProductItemList
				.map((v, index) => {
					map.set(v.projectProductItemId, index)
					return v.projectProductItemId
				})
				.filter(Boolean)
			const son = objectIds.length
				? await bizProcessApi.bizProcessProjectProductItemRelationList({ id: props.id, objectIds })
				: []

			son.forEach((v) => {
				let current = baseInfo.value.projectProductItemList[map.get(v.objectId)]
				if (!current.children) {
					current.children = []
				}
				v.productName = safeJsonParse(v.extJson, {}).product?.productName || v.productName

				v.amount = v.number
				current.children.push(v)
			})
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	load()
</script>

<style scoped></style>
