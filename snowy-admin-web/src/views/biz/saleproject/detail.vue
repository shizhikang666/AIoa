<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="项目详细信息"
		:width="'70%'"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<template v-if="!error">
			<!--占位loading-->
			<br v-if="loading" />
			<a-skeleton active :loading="loading">
				<a-tabs v-model:active-key="activeComponents">
					<a-tab-pane key="baseInfo" tab="项目信息">
						<a-descriptions bordered title="项目信息" size="small">
							<a-descriptions-item label="项目名称">{{ projectBaseInfo.projectName }} </a-descriptions-item>
							<a-descriptions-item label="项目编号">{{ projectBaseInfo.projectCode }} </a-descriptions-item>
							<a-descriptions-item label="项目地区">{{ projectBaseInfo.area }}</a-descriptions-item>
							<a-descriptions-item label="项目状态">
								<a-tag
									:color="
										$TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE_COLOR', projectBaseInfo.projectState)
									"
								>
									{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE', projectBaseInfo.projectState) }}
								</a-tag>
							</a-descriptions-item>
							<a-descriptions-item v-if="projectBaseInfo.projectState === 'DISCARD'" label="作废原因">
								<span
									:style="{
										color: 'red'
									}"
								>
									{{ projectBaseInfo.repealContent }}
								</span>
							</a-descriptions-item>
							<a-descriptions-item label="项目显示状态">
								{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_VISIBILITY', projectBaseInfo.visibility) }}
							</a-descriptions-item>
							<a-descriptions-item label="项目类别">
								{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'PROJECT_CATEGORY', projectBaseInfo.projectCategory) }}
							</a-descriptions-item>
							<a-descriptions-item label="项目负责人">
								{{ projectBaseInfo.headName }}
							</a-descriptions-item>
							<a-descriptions-item :span="6" label="项目创建日期">
								{{ projectBaseInfo.createTime }}
							</a-descriptions-item>
							<a-descriptions-item :span="6" label="成交日期">
								{{ projectBaseInfo.completionDate }}
							</a-descriptions-item>
							<a-descriptions-item :span="6" label="备注">
								{{ projectBaseInfo.remark }}
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" :span="4" label="收款账户">
								{{ projectBaseInfo.accountName }}
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" :span="4" label="结算方式">
								{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'payerCategory', projectBaseInfo.payerCategory) }}
							</a-descriptions-item>
							<a-descriptions-item label="回扣金额">
								<a-typography-text style="padding-right: 6px" strong
									>￥ {{ projectBaseInfo.rebateAmount }}
								</a-typography-text>
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="初始金额">
								<a-typography-text style="padding-right: 6px">￥ {{ projectBaseInfo.initPrice }} </a-typography-text>
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="项目金额">
								<a-typography-text style="padding-right: 6px" strong
									>￥ {{ projectBaseInfo.totalPrice }}
								</a-typography-text>
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="累计收款金额">
								￥ {{ projectBaseInfo.amountCollected }}
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="退货减款">
								￥ {{ projectBaseInfo.totalRefundAmount || 0 }}
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="已退款">
								￥ {{ projectBaseInfo.totalReturnAmount || 0 }}
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="净收款">
								￥ {{ netCollected }}
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="实际金额">
								{{ dealAmount }}
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="待收款">
								<a-typography-text :type="Number(pendingCollection) > 0 ? 'danger' : 'success'">
									￥ {{ pendingCollection }}
								</a-typography-text>
							</a-descriptions-item>
							<a-descriptions-item v-if="isDeal" label="待退款">
								<a-typography-text :type="Number(pendingRefund) > 0 ? 'warning' : 'success'">
									￥ {{ pendingRefund }}
								</a-typography-text>
							</a-descriptions-item>
						</a-descriptions>
						<br />
						<template v-if="isDeal">
							<a-typography-title :level="5">附件信息</a-typography-title>
							<a-image-preview-group>
								<a-image
									:key="item.id"
									style="border: 1px solid rgba(67, 67, 67, 0.45)"
									v-for="item in imgList"
									:height="100"
									:src="item.downloadPath"
								/>
							</a-image-preview-group>
							<br /><br />
							<a-list item-layout="horizontal" :data-source="otherFileList">
								<template #renderItem="{ item }">
									<a-list-item key="item.id">
										<a-comment>
											<template #author
												><a>{{ item.createUserName }}</a></template
											>
											<template #avatar>
												<a-avatar :src="item.avatar" :alt="item.createUserName" />
											</template>
											<template #content>
												<p>创建时间：{{ item.createTime }}</p>
												<p>
													<a-space>
														<span> 文件名称：{{ item.name }}</span> <span>大小：{{ item.sizeKb }} kb</span>
														<a-typography-link :href="item.downloadPath">下载 </a-typography-link>
														<a-typography-link @click="openFilePreview(item)">预览 </a-typography-link>
													</a-space>
												</p>
											</template>
											<template #datetime>
												<a-tooltip :title="item.createTime">
													<span>{{ calcTime(item.createTime) }}</span>
												</a-tooltip>
											</template>
										</a-comment>
									</a-list-item>
								</template>
							</a-list>
							<a-typography-title v-if="changeLogs.length > 0" :level="5">变更记录</a-typography-title>
							<a-list v-if="changeLogs.length > 0" item-layout="horizontal" :data-source="changeLogs">
								<template #renderItem="{ item }">
									<a-list-item key="item.id">
										<a-comment>
											<template #author
												><a>{{ item.createUserName }}</a></template
											>

											<template #content>
												<p>{{ item.fieldLabel }} 由 {{ item.beforeValue }} 变更{{ item.afterValue }}</p>
												<p>{{ item.changeReason }}</p>
											</template>
											<template #datetime>
												<a-tooltip :title="item.createTime">
													<span>{{ item.createTime }}</span>
												</a-tooltip>
											</template>
										</a-comment>
									</a-list-item>
								</template>
							</a-list>
							<br />
							<a-descriptions
								bordered
								title="开票信息"
								:key="invoicingInfo.id"
								v-for="invoicingInfo in invoicingList"
								size="small"
							>
								<a-descriptions-item label="开票金额">
									{{ invoicingInfo.amount }}
								</a-descriptions-item>
								<a-descriptions-item :span="4" label="开票类型">
									{{ $TOOL.dictTypeDataByPath('InvoicingCategory', invoicingInfo.invoicingCategory) }}
								</a-descriptions-item>
								<a-descriptions-item :span="4" label="开票公司">
									{{ invoicingInfo.companyName }}
								</a-descriptions-item>
								<a-descriptions-item :span="4" label="客户公司">
									{{ invoicingInfo.customerCompany }}
								</a-descriptions-item>
								<a-descriptions-item label="单位全称">
									{{ invoicingInfo.unit }}
								</a-descriptions-item>
								<a-descriptions-item label="单位电话">
									{{ invoicingInfo.unitPhone }}
								</a-descriptions-item>
								<a-descriptions-item label="纳税人号">
									{{ invoicingInfo.taxpayer }}
								</a-descriptions-item>
								<a-descriptions-item label="对公账户">
									{{ invoicingInfo.corporateAccount }}
								</a-descriptions-item>
								<a-descriptions-item label="开户银行">
									{{ invoicingInfo.bankName }}
								</a-descriptions-item>
								<a-descriptions-item label="单位地址">
									{{ invoicingInfo.unitAddress }}
								</a-descriptions-item>
								<a-descriptions-item label="发票收货地址">
									{{ invoicingInfo.harvestAddress }}
								</a-descriptions-item>
								<a-descriptions-item label="发票收货电话">
									{{ invoicingInfo.phone }}
								</a-descriptions-item>
								<a-descriptions-item label="备注">
									{{ invoicingInfo.remark }}
								</a-descriptions-item>
							</a-descriptions>
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
							</a-descriptions>
							<br />
							<br />
							<a-table
								:pagination="false"
								size="middle"
								bordered
								:data-source="projectProductItemList"
								:columns="columns"
								rowKey="id"
							>
								<template #bodyCell="{ column, text, record }">
									<template v-if="column.dataIndex === 'productName'">
										{{ text }} &nbsp;&nbsp;
										<a-typography-text type="danger" v-if="record.isReturn">
											有退货数量（{{ record.returnAmount }}）
										</a-typography-text>
									</template>
									<template v-if="column.dataIndex === 'productCategory'">
										{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
									</template>
									<template v-if="column.dataIndex === 'productSysCategory'">
										<a-tag
											:color="
												$TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE_COLOR', record.productSysCategory)
											"
										>
											{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE', record.productSysCategory) }}
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
								</template>
								<template #footer>
									<a-row justify="end">
										共计：
										<a-typography-text style="padding-right: 6px" strong>￥{{ totalPrice }} </a-typography-text>
									</a-row>
								</template>
							</a-table>
						</template>
						<br />
						<template :key="item.order.id" v-for="item in reissueOrderList">
							<a-descriptions bordered :title="`补发单(${item.order.createTime})`" size="small">
								<a-descriptions-item label="创建人">{{ item.order.createUserName }} </a-descriptions-item>
								<a-descriptions-item label="补发状态">
									<a-tag :color="reissueStatusColor(item.order.shipmentStatus)">
										{{ reissueStatusText(item.order.shipmentStatus) }}
									</a-tag>
									<span v-if="item.order.pendingQuantity">待发 {{ item.order.pendingQuantity }}</span>
								</a-descriptions-item>
								<a-descriptions-item label="增加金额">{{ item.order.amount }}</a-descriptions-item>

								<a-descriptions-item label="备注">{{ item.order.remark }}</a-descriptions-item>
							</a-descriptions>
							<br />
							<a-table
								:pagination="false"
								size="middle"
								bordered
								:data-source="item.productItemList"
								:columns="columns"
								rowKey="id"
							>
								<template #bodyCell="{ column, text, record }">
									<template v-if="column.dataIndex === 'productName'">
										{{ text }} &nbsp;&nbsp;
										<a-typography-text type="danger" v-if="record.isReturn">
											有退货数量（{{ record.returnAmount }}）
										</a-typography-text>
									</template>
									<template v-if="column.dataIndex === 'productCategory'">
										{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
									</template>
									<template v-if="column.dataIndex === 'productSysCategory'">
										<a-tag
											:color="
												$TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE_COLOR', record.productSysCategory)
											"
										>
											{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE', record.productSysCategory) }}
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
								</template>
							</a-table>
							<br />
							<br /><br />
						</template>
						<a-descriptions bordered title="客户信息" size="small">
							<a-descriptions-item label="客户姓名">{{ customerBaseInfo.name }}</a-descriptions-item>
							<a-descriptions-item label="创建时间">{{ customerBaseInfo.createTime }} </a-descriptions-item>
							<a-descriptions-item label="创建人">{{ customerBaseInfo.createUserName }} </a-descriptions-item>
							<a-descriptions-item label="联系人">{{ customerBaseInfo.contacts }}</a-descriptions-item>
							<a-descriptions-item label="联系电话">{{ customerBaseInfo.phone }}</a-descriptions-item>
							<a-descriptions-item label="地址">{{ customerBaseInfo.address }}</a-descriptions-item>
							<a-descriptions-item label="详细地址">{{ customerBaseInfo.detailsAddress }} </a-descriptions-item>
							<a-descriptions-item label="客户来源">
								{{ $TOOL.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_SOURCE', customerBaseInfo.sourceType) }}
							</a-descriptions-item>
							<a-descriptions-item label="客户类型">
								{{ $TOOL.dictTypeDataByPath('CUSTOMER', 'CUSTOMER_TYPE', customerBaseInfo.customType) }}
							</a-descriptions-item>
							<a-descriptions-item label="负责人">{{ customerBaseInfo.headName }}</a-descriptions-item>
							<a-descriptions-item label="所属部门组织">{{ customerBaseInfo.orgName }} </a-descriptions-item>
							<a-descriptions-item label="备注">{{ customerBaseInfo.remark }}</a-descriptions-item>
						</a-descriptions>
					</a-tab-pane>
					<a-tab-pane key="bizSaleProjectCost" tab="成本核算" v-if="isDeal && hasPerm('bizSaleProjectCost')">
						<BizSaleProjectCost :projectInfo="projectBaseInfo" :project-id="projectBaseInfo.id"></BizSaleProjectCost>
					</a-tab-pane>
					<a-tab-pane key="followUpRecords" tab="项目跟进记录">
						<followup :project-id="projectBaseInfo.id"></followup>
					</a-tab-pane>
					<a-tab-pane v-if="isDeal" key="projectFile" tab="项目附件">
						<projectFile :project-id="projectBaseInfo.id"></projectFile>
					</a-tab-pane>

					<a-tab-pane key="payment" tab="收款记录" v-if="isDeal">
						<payment :project-id="projectBaseInfo.id"></payment>
					</a-tab-pane>
					<a-tab-pane key="invoiceRecords" tab="发货记录" v-if="isDeal">
						<projectInvoice :project-id="projectBaseInfo.id" />
					</a-tab-pane>
					<a-tab-pane key="returnOrders" tab="退货记录" v-if="isDeal">
						<ReturnOrderDetails :project-id="projectBaseInfo.id"></ReturnOrderDetails>
					</a-tab-pane>
					<a-tab-pane key="projectCase" tab="项目案例">
						<project-case :project="projectBaseInfo" :project-id="projectBaseInfo.id"></project-case>
					</a-tab-pane>
					<a-tab-pane key="projectProcess">
						<template #tab>
							<a-badge :offset="[10, 0]" :count="processCount">
								<span> 审核中的流程 </span>
							</a-badge>
						</template>

						<project-process :id="projectBaseInfo.id"></project-process>
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
			<a-space>
				<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
				<a-button
					v-if="isShowStartProjectReissueButton"
					danger
					@click="startProjectReissueFlowFormRef.onOpen(projectBaseInfo)"
					>换货补发
				</a-button>

				<a-button
					@click="startProjectReturnFlowFormRef.onOpen(projectBaseInfo)"
					v-if="isShowReturnProjectProduct"
					danger
					type="primary"
					>退货
				</a-button>
				<a-button
					v-if="isDeal && hasPerm('bizSaleProjectExportDeliveryNote')"
					@click="exportWord"
					:loading="exportWordLoading"
					type="primary"
				>
					导出发货单
				</a-button>
			</a-space>
		</template>
	</xn-form-container>

	<start-project-reissue-flow-form ref="startProjectReissueFlowFormRef"></start-project-reissue-flow-form>
	<start-project-return-flow-form ref="startProjectReturnFlowFormRef"></start-project-return-flow-form>
</template>
<script setup name="projectDetails">
	import customerApi from '@/api/biz/customerApi'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import followup from './saleProjectTab/followup/index.vue'
	import payment from './saleProjectTab/payment/index.vue'
	import projectInvoice from './saleProjectTab/invoice/index.vue'
	import projectFile from './saleProjectTab/file/index.vue'
	import bizSaleProjectReissueOrderApi from '@/api/biz/bizSaleProjectReissueOrderApi'
	import projectCase from './saleProjectTab/projectCase/index.vue'
	import projectProcess from './saleProjectTab/process/index.vue'
	import { computed, useTemplateRef } from 'vue'
	import { Decimal } from 'decimal.js'
	import { useProject } from '@/composables/useProject'
	import BizFileRelationApi from '@/api/biz/bizFileRelationApi'
	import ReturnOrderApi from '@/api/biz/returnOrderApi'
	import { openFilePreview } from '@/utils/filePreview'
	import dayjs from '@/utils/dayjs'
	import { useLoading } from '@/composables/useLoading'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import StartProjectReissueFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectReissueFlowForm.vue'
	import StartProjectReturnFlowForm from '@/views/biz/bizprocess/processForm/project/startProjectReturnFlowForm.vue'
	import ReturnOrderDetails from './saleProjectTab/returnOrder/index.vue'
	import BizSaleProjectCost from './saleProjectTab/cost/index.vue'

	const startProjectReissueFlowFormRef = useTemplateRef('startProjectReissueFlowFormRef')
	const startProjectReturnFlowFormRef = useTemplateRef('startProjectReturnFlowFormRef')
	const { exportProjectInitInvoice } = useProject()
	const calcTime = (time) => {
		return dayjs(time).fromNow()
	}
	const isShowStartProjectReissueButton = computed(() => {
		const { projectState } = projectBaseInfo.value
		return projectState === 'PARTIALLY_SHIPPED' || projectState === 'SHIPPED' || projectState === 'COMPLETED'
	})

	const isShowReturnProjectProduct = computed(() => {
		const { projectState } = projectBaseInfo.value
		return projectState === 'SHIPPED' || projectState === 'PARTIALLY_SHIPPED' || projectState === 'COMPLETED'
	})

	const processCount = ref(0)
	const fileList = ref([])
	const open = ref(false)
	const loading = ref(false)
	const error = ref(false)
	const customerBaseInfo = ref({})
	const projectBaseInfo = ref({})
	const projectProductItemList = ref([])
	const activeComponents = ref('baseInfo')
	const invoicingList = ref([])
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
			title: '已发货数量',

			dataIndex: 'delivery'
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
			title: '价格',

			dataIndex: 'price'
		},
		{
			title: '备注',

			dataIndex: 'remark'
		},
		{
			title: '状态',

			dataIndex: 'state'
		}
	]
	const reissueOrderList = ref([])
	const reissueStatusText = (status) => {
		return {
			WAIT_REISSUE: '待补发',
			PARTIALLY_REISSUED: '部分补发',
			REISSUED: '补发完成'
		}[status] || '待补发'
	}
	const reissueStatusColor = (status) => {
		return {
			WAIT_REISSUE: 'orange',
			PARTIALLY_REISSUED: 'blue',
			REISSUED: 'green'
		}[status] || 'orange'
	}
	const { isDeal } = useProject(projectBaseInfo)
	const netCollected = computed(() =>
		new Decimal(projectBaseInfo.value?.amountCollected || 0)
			.sub(projectBaseInfo.value?.totalReturnAmount || 0)
			.toFixed(2)
	)
	const pendingCollection = computed(() =>
		Decimal.max(new Decimal(projectBaseInfo.value?.totalPrice || 0).sub(netCollected.value), 0).toFixed(2)
	)
	const pendingRefund = computed(() =>
		Decimal.max(new Decimal(netCollected.value).sub(projectBaseInfo.value?.totalPrice || 0), 0).toFixed(2)
	)
	const dealAmount = computed(() => {
		let rebateAmount = projectBaseInfo.value?.rebateAmount ? projectBaseInfo.value?.rebateAmount : 0

		let result = new Decimal(projectBaseInfo.value?.totalPrice).sub(new Decimal(rebateAmount))

		return result.toString()
	})
	const totalPrice = computed(() => {
		return projectProductItemList.value.reduce((sum, item) => {
			return sum.plus(new Decimal(item.price ? item.price : 0))
		}, new Decimal(0))
	})

	const imgSuffix = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'webp']

	const imgList = computed(() => {
		return fileList.value.filter((v) => {
			return imgSuffix.includes(v.suffix)
		})
	})
	const otherFileList = computed(() => {
		return fileList.value.filter((v) => {
			return !imgSuffix.includes(v.suffix)
		})
	})
	const changeLogs = ref([])
	const onOpen = async (record, config) => {
		open.value = true

		loading.value = true
		error.value = false
		try {
			// 并发获取多个数据源，提升性能
			const [projectDetail, reissueOrderListResult, fileListResult, processResult, returnOrderListResult] =
				await Promise.all([
					bizSaleProjectApi.bizSaleProjectDetail({ id: record.id }),
					bizSaleProjectReissueOrderApi.bizSaleProjectReissueOrderListDetail({ projectId: record.id }),
					BizFileRelationApi.bizFileRelationList({ objectId: record.id, category: 'SALE_PROJECT' }),
					bizProcessApi.bizProcessProjectRuntimeQueryList({ projectId: record.id }),
					ReturnOrderApi.returnOrderQuery({ projectId: record.id })
				])

			// 获取客户信息
			const customerBaseInfoResult = await customerApi.customerDetail({ id: projectDetail.bizSaleProject.customer })
			// 处理退货数据
			const returnMap = returnOrderListResult.reduce((map, item) => {
				item.productList.forEach((product) => {
					map[product.projectProductItemId] = (map[product.projectProductItemId] || 0) + product.amount
				})
				return map
			}, {})
			// 更新项目基本信息
			projectBaseInfo.value = projectDetail.bizSaleProject
			changeLogs.value = projectDetail.changeLogs
			projectProductItemList.value = projectDetail.productItems
				.filter((item) => item.category !== 'REISSUE_ORDER')
				.map((item) => ({
					...item,
					isReturn: !!returnMap[item.id],
					returnAmount: returnMap[item.id] || 0
				}))
			// 更新补发单列表
			reissueOrderList.value = reissueOrderListResult.map((item) => ({
				...item,
				productItemList: item.productItemList.map((product) => ({
					...product,
					isReturn: !!returnMap[product.id],
					returnAmount: returnMap[product.id] || 0
				}))
			}))

			// 更新其他数据
			customerBaseInfo.value = customerBaseInfoResult
			fileList.value = fileListResult

			invoicingList.value = projectDetail.invoicingList
			processCount.value = processResult.length
			if (config && config.route) {
				activeComponents.value = config.route
			}
		} catch (e) {
			console.error('数据加载失败:', e)
			error.value = true
			throw e
		} finally {
			loading.value = false
		}
	}

	const onClose = () => {
		open.value = false
		activeComponents.value = 'baseInfo'
	}
	const { load: exportWord, loading: exportWordLoading } = useLoading(async () => {
		await exportProjectInitInvoice(projectBaseInfo.value.id)
	})
	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
