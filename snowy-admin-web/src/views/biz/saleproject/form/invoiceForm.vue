<script setup lang="js">
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import customerApi from '@/api/biz/customerApi'
	import bizSaleProjectReissueOrderApi from '@/api/biz/bizSaleProjectReissueOrderApi'
	import BizFileRelationApi from '@/api/biz/bizFileRelationApi'
	import { useLoading } from '@/composables/useLoading'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import { useProduct } from '@/composables/useProduct'
	import projectInvoice from '../saleProjectTab/invoice/index.vue'
	import { useProject } from '@/composables/useProject'
	import SaleProjectProductItemRelationApi from '@/api/biz/saleProjectProductItemRelationApi'
	import bizSaleProjectProductItemApi from '@/api/biz/bizSaleProjectProductItemApi'

	const { exportProjectInitInvoice } = useProject()
	const projectProductItemList = ref([])
	const projectBaseInfo = ref({})
	const reissueOrderList = ref([])
	const activeKey = ref('baseInfo')
	const open = ref(false)
	const onClose = () => {
		open.value = false
	}
	const { warpProduct } = useProduct()
	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			key: 'productId'
		},
		{
			title: '产品系统分类',
			dataIndex: 'productSysCategory',
			width: 150
		},
		{
			title: '产品分类',
			dataIndex: 'productCategory',
			width: 100
		},
		{
			title: '规格',
			dataIndex: 'productSpecs',
			width: 50
		},
		{
			title: '数量',

			dataIndex: 'number',
			width: 50
		},
		{
			title: '已发货数量',

			dataIndex: 'delivery',
			width: 150
		},

		{
			title: '备注',

			dataIndex: 'remark'
		},
		{
			title: '标记',
			dataIndex: 'mark',
			width: 150
		},
		{
			title: '状态',

			width: 50,
			dataIndex: 'state'
		}
	]
	const {
		load: onOpen,
		loading,
		error
	} = useLoading(async (record) => {
		open.value = true
		//获取项目基本信息
		const result = await bizSaleProjectApi.bizSaleProjectDetail({ id: record.id })
		activeKey.value = 'baseInfo'
		//获取销售项目补发单列表
		const reissueOrderListResult = await bizSaleProjectReissueOrderApi.bizSaleProjectReissueOrderListDetail({
			projectId: record.id
		})

		reissueOrderList.value = reissueOrderListResult
		//获取项目基本信息
		projectBaseInfo.value = result?.bizSaleProject
		projectProductItemList.value = result?.productItems
	})

	const { load: exportWord, loading: exportWordLoading } = useLoading(async () => {
		await exportProjectInitInvoice(projectBaseInfo.value.id)
	})

	const setMark = async (record, mark) => {
		if (record.objectId) {
			await SaleProjectProductItemRelationApi.saleProjectProductItemRelationEditMark({
				id: record.id,
				mark
			})
		} else {
			await bizSaleProjectProductItemApi.saleProjectProductItemEditMark({
				id: record.id,
				mark
			})
		}
		record.mark = mark
	}

	defineExpose({
		onOpen
	})
</script>

<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="项目发货单"
		:width="'100%'"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="loading">
			<error-result v-if="error"></error-result>
			<a-tabs v-else v-model:active-key="activeKey">
				<a-tab-pane key="baseInfo" tab="发货表单">
					<br />
					<a-descriptions bordered title="收货信息" size="small">
						<a-descriptions-item label="联系人">
							{{ projectBaseInfo.consignee }}
						</a-descriptions-item>
						<a-descriptions-item :span="4" label="联系电话">
							{{ projectBaseInfo.phone }}
						</a-descriptions-item>
						<a-descriptions-item :span="4" label="收货单位">
							{{ projectBaseInfo.unit }}
						</a-descriptions-item>
						<a-descriptions-item :span="4" label="收货地址">
							{{ projectBaseInfo.address }}
						</a-descriptions-item>
						<a-descriptions-item label="运费支付方式">
							{{ $TOOL.dictTypeDataByPath('FREIGHT_CATEGORY', projectBaseInfo.freightCategory) }}
						</a-descriptions-item>
						<a-descriptions-item label="运费">
							{{ projectBaseInfo.freight }}
						</a-descriptions-item>
						<a-descriptions-item label="指定物流信息">
							{{
								projectBaseInfo.logisticsCategory
									? $TOOL.dictTypeDataByPath('LOGISTICS_CATEGORY', projectBaseInfo.logisticsCategory)
									: '无'
							}}
						</a-descriptions-item>

						<a-descriptions-item label="备注">
							{{ projectBaseInfo.deliveryNote }}
						</a-descriptions-item>
					</a-descriptions>
					<br />
					<a-table
						:defaultExpandAllRows="true"
						rowKey="productId"
						:pagination="false"
						size="middle"
						bordered
						:data-source="projectProductItemList"
						:columns="columns"
					>
						<template #bodyCell="{ column, text, record, index }">
							<template v-if="column.dataIndex === 'productCategory'">
								{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
							</template>
							<template v-if="column.dataIndex === 'productSysCategory'">
								<a-tag
									:color="
										$TOOL.dictTypeDataByPath(
											'PRODUCT_DICT',
											'PRODUCT_SYS_TYPE_COLOR',
											record.productSysCategory || record.category
										)
									"
								>
									{{
										$TOOL.dictTypeDataByPath(
											'PRODUCT_DICT',
											'PRODUCT_SYS_TYPE',
											record.productSysCategory || record.category
										)
									}}
								</a-tag>
							</template>
							<template v-if="column.dataIndex === 'productSpecs'">
								{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SPECS', record.productSpecs || record.specs) }}
							</template>
							<template v-if="column.dataIndex === 'state'">
								<a-tag
									v-if="record.state"
									:color="$TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE_COLOR', record.state)"
								>
									{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_ITEM_STATE', record.state) }}
								</a-tag>
							</template>
							<template v-if="column.dataIndex === 'mark'">
								<a-popconfirm title="请选择标记">
									<a v-if="!record.mark">添加标记</a>
									<a v-else>{{ record.mark }}</a>
									<template #okButton>
										<a-space>
											<a-button @click="setMark(record, '✔')" size="small">✔</a-button>
											<a-button @click="setMark(record, '✖')" size="small">✖</a-button>
										</a-space>
									</template>
								</a-popconfirm>
							</template>
						</template>
					</a-table>
					<br />
					<template :key="item.order.id" v-for="(item, i) in reissueOrderList">
						<a-descriptions bordered :title="`补发单(${item.order.createTime})`" size="small">
							<a-descriptions-item label="创建人">{{ item.order.createUserName }}</a-descriptions-item>

							<a-descriptions-item label="备注">{{ item.order.remark }}</a-descriptions-item>
						</a-descriptions>
						<br />
						<a-table
							rowKey="productId"
							:pagination="false"
							size="middle"
							bordered
							:data-source="item.productItemList"
							:columns="columns"
						>
							<template #bodyCell="{ column, text, record, index }">
								<template v-if="column.dataIndex === 'productCategory'">
									{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
								</template>
								<template v-if="column.dataIndex === 'productSpecs'">
									{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SPECS', record.productSpecs || record.specs) }}
								</template>
								<template v-if="column.dataIndex === 'productSysCategory'">
									<a-tag
										:color="
											$TOOL.dictTypeDataByPath(
												'PRODUCT_DICT',
												'PRODUCT_SYS_TYPE_COLOR',
												record.productSysCategory || record.category
											)
										"
									>
										{{
											$TOOL.dictTypeDataByPath(
												'PRODUCT_DICT',
												'PRODUCT_SYS_TYPE',
												record.productSysCategory || record.category
											)
										}}
									</a-tag>
								</template>
								<template v-if="column.dataIndex === 'state'">
									<a-tag
										v-if="record.state"
										:color="$TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE_COLOR', record.state)"
									>
										{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_ITEM_STATE', record.state) }}
									</a-tag>
								</template>
								<template v-if="column.dataIndex === 'mark'">
									<a-popconfirm title="请选择标记">
										<a v-if="!record.mark">添加标记</a>
										<a v-else>{{ record.mark }}</a>
										<template #okButton>
											<a-space>
												<a-button @click="setMark(record, '✔')" size="small">✔</a-button>
												<a-button @click="setMark(record, '✖')" size="small">✖</a-button>
											</a-space>
										</template>
									</a-popconfirm>
								</template>
							</template>
						</a-table>
						<br />
						<br /><br />
					</template>
				</a-tab-pane>
				<a-tab-pane key="invoiceRecords" tab="发货记录">
					<projectInvoice :project-id="projectBaseInfo.id" />
				</a-tab-pane>
			</a-tabs>
		</a-skeleton>

		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button @click="exportWord" :loading="exportWordLoading" type="primary"> 导出发货单</a-button>
		</template>
	</xn-form-container>
</template>

<style scoped></style>
