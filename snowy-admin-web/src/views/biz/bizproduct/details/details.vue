<template>
	<xn-form-container
		title="产品详细信息"
		:width="1000"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
		:bodyStyle="{ paddingTop: 0 }"
	>
		<a-skeleton active :loading="loading">
			<a-tabs v-model:activeKey="activeKey">
				<a-tab-pane key="baseInfo" tab="基本信息">
					<a-descriptions bordered title="基本信息" size="small">
						<a-descriptions-item label="产品名称">{{ result.bizProduct.productName }}</a-descriptions-item>

						<a-descriptions-item label="产品类型">
							<a-tag
								:color="$TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE_COLOR', result.bizProduct.category)"
							>
								{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE', result.bizProduct.category) }}
							</a-tag>
						</a-descriptions-item>
						<a-descriptions-item label="产品分类">
							{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', result.bizProduct.productCategory) }}
						</a-descriptions-item>
						<a-descriptions-item label="最低销售额">{{ result.bizProduct.minPrice }}</a-descriptions-item>
						<a-descriptions-item label="安全库存">{{ result.bizProduct.safetyStock }}</a-descriptions-item>
						<a-descriptions-item label="售价">{{ result.bizProduct.salePrice }}</a-descriptions-item>
						<a-descriptions-item label="采购价">{{ result.bizProduct.purchasePrice }}</a-descriptions-item>
						<a-descriptions-item span="4" label="封面图片">
							<a-image
								v-if="result.bizProduct.coverImage"
								:width="200"
								:height="200"
								:src="result.bizProduct.coverImage"
								fallback="data:image/png;base64,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"
							></a-image>

							<a-empty v-else></a-empty>
						</a-descriptions-item>
					</a-descriptions>
					<br />
					<br />
					<br />
					<a-typography-title :level="5">套件信息</a-typography-title>
					<br />
					<a-table :columns="columns" :data-source="result.productList">
						<template #bodyCell="{ column, record }">
							<template v-if="column.dataIndex === 'productName'">
								<a-typography-link @click="details.onOpen(record.product)"
									>{{ record.product.productName }}
								</a-typography-link>
							</template>
							<template v-if="column.dataIndex === 'productCategory'">
								{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.product.productCategory) }}
							</template>
							<template v-if="column.dataIndex === 'category'">
								<a-tag
									:color="$TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE_COLOR', record.product.category)"
								>
									{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE', record.product.category) }}
								</a-tag>
							</template>
						</template>
					</a-table>
					<a-result v-if="error" status="500" title="500" sub-title="服务器错误！">
						<template #extra>
							<a-button type="primary" @click="loadData">重新加载</a-button>
						</template>
					</a-result>
				</a-tab-pane>
				<a-tab-pane key="inventory" tab="出入库记录">
					<inventory-info :product-id="record.id"></inventory-info>
				</a-tab-pane>
			</a-tabs>
		</a-skeleton>
	</xn-form-container>
	<productDetail ref="details" v-if="result.productList.length > 0"></productDetail>
</template>
<script setup name="productDetail">
	import { ref } from 'vue'
	import bizProductApi from '@/api/biz/bizProductApi'
	import tool from '@/utils/tool'
	import InventoryInfo from '@/views/biz/bizproduct/details/inventoryInfo/inventoryInfo.vue'
	import { Empty } from 'ant-design-vue'

	const open = ref(false)
	const activeKey = ref('baseInfo')
	const emit = defineEmits({ successful: null })
	const productCategoryOptions = ref([])
	const categoryOptions = ref([])
	let record = ref({})
	productCategoryOptions.value = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_TYPE'])
	categoryOptions.value = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_SYS_TYPE'])
	// 打开抽屉
	const onOpen = (record_object) => {
		record.value = record_object
		open.value = true
		loadData()
	}
	const details = ref()

	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName'
		},
		{
			title: '产品分类',
			dataIndex: 'productCategory'
		},

		{
			title: '数量',
			dataIndex: 'number'
		},

		{
			title: '类别',
			dataIndex: 'category'
		}
	]

	// 关闭抽屉
	const onClose = () => {
		open.value = false
	}

	const loading = ref(false)
	const error = ref(false)
	const result = ref({
		bizProduct: {},
		productList: []
	})

	const loadData = async () => {
		try {
			loading.value = true
			result.value = await bizProductApi.bizProductDetail({ id: record.value.id })
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
