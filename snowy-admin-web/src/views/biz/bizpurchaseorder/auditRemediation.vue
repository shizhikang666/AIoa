<template>
	<xn-form-container title="审计修复采购单" :width="1000" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-skeleton active :loading="loading">
			<a-form class="product-form" ref="formRef" :model="formData" :rules="formRules" layout="horizontal">
				<a-form-item label="采购金额：" name="amount">
					<XnCurrencyInput :min="0.01" v-model:value="formData.amount" placeholder="请输入采购金额" />
				</a-form-item>
				<a-table
					class="add_table"
					rowKey="projectProductItemId"
					:pagination="false"
					size="middle"
					bordered
					:data-source="formData.productList"
					:columns="columns"
				>
					<template #bodyCell="{ column, text, record, index }">
						<template v-if="column.dataIndex === 'productName'">
							{{ record.productName }}
						</template>
						<template v-if="column.dataIndex === 'productCategory'">
							{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
						</template>
						<template v-if="column.dataIndex === 'number'"></template>

						<template v-if="column.dataIndex === 'unitAmount'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'unitAmount']"
								:rules="{ required: true, message: '单价必填', trigger: 'change' }"
							>
								<a-input-number
									min="1"
									@change="changeProductNumber(index)"
									v-model:value="formData.productList[index].unitAmount"
									placeholder=""
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>

						<template v-if="column.dataIndex === 'discountRate'">
							<a-form-item
								:key="formData.productList[index].id"
								@change="changeProductNumber(index)"
								style="margin-bottom: 0"
								:name="['productList', index, 'discountRate']"
								:rules="{ required: true, message: '必须填写', trigger: 'change' }"
							>
								<a-input-number
									:precision="2"
									:formatter="(value) => `${value}%`"
									:parser="(value) => value.replace('%', '')"
									@change="changeProductNumber(index)"
									min="0"
									v-model:value="formData.productList[index].discountRate"
									placeholder="优惠率"
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>

						<template v-if="column.dataIndex === 'amount'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'amount']"
								:rules="{ required: true, message: '数量必填', trigger: 'change' }"
							>
								<a-input-number
									min="1"
									v-model:value="formData.productList[index].amount"
									placeholder=""
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>
						<template v-if="column.dataIndex === 'freightShareAmount'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'freightShareAmount']"
							>
								<a-input-number
									@change="changeProductNumber(index)"
									min="1"
									v-model:value="formData.productList[index].freightShareAmount"
									placeholder=""
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>
						<template v-if="column.dataIndex === 'unitCostWithFreight'">
							<a-form-item
								@change="changeProductNumber(index)"
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'unitCostWithFreight']"
							>
								<a-input-number
									min="1"
									v-model:value="formData.productList[index].unitCostWithFreight"
									placeholder=""
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>

						<template v-if="column.dataIndex === 'remark'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'remark']"
							>
								<a-input v-model:value="formData.productList[index].remark"></a-input>
							</a-form-item>
						</template>
					</template>
					<template #footer>
						<a-row justify="end">
							<a-form-item label="" name="amount"> ¥{{ totalPrice }} </a-form-item>
						</a-row>
					</template>
				</a-table>
				<!--				<h1>支出记录</h1>-->
				<a-table
					:pagination="false"
					size="middle"
					bordered
					:data-source="formData.bizExpenditureRecordList"
					:columns="excolumns"
				>
					<template #bodyCell="{ column, text, record, index }">
						<template v-if="column.dataIndex === 'settlementCategory'">
							{{
								$TOOL.dictTypeDataByPath(
									'SETTLEMENT_ACCOUNT',
									'SETTLEMENT_CATEGORY',
									'PAY_CATEGORY',
									record.settlementCategory
								)
							}}
						</template>
					</template>
					<template #footer>
						<a-row justify="end">
							共计：
							<a-typography-text style="padding-right: 6px" strong>￥{{ totalAmount }} </a-typography-text>
						</a-row>
					</template>
				</a-table>
			</a-form>
		</a-skeleton>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizPurchaseOrderForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	import { useLoading } from '@/composables/useLoading'
	import { Decimal } from 'decimal.js'
	import { computed } from 'vue'
	// 创建统一的金额计算工具
	const moneyUtils = {
		// 元转分（避免浮点数问题）
		yuanToCents: (yuan) => Math.round(parseFloat(yuan || 0) * 100),

		// 分转元
		centsToYuan: (cents) => (cents / 100).toFixed(2),

		// 安全加法
		add: (...amounts) => {
			const totalCents = amounts.reduce((sum, amount) => {
				return sum + Math.round(parseFloat(amount || 0) * 100)
			}, 0)
			return totalCents / 100
		},

		// 安全乘法
		multiply: (a, b) => {
			const aCents = Math.round(parseFloat(a || 0) * 100)
			const result = (aCents * parseFloat(b || 0)) / 100
			return Math.round(result * 100) / 100
		}
	}

	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const totalAmount = computed(() => {
		const records = formData.value?.bizExpenditureRecordList || []

		// 使用整数计算避免浮点数精度问题
		const totalInCents = records.reduce((total, record) => {
			const amount = parseFloat(record?.amount) || 0
			// 转换为分（或其他最小单位）进行计算
			return total + Math.round(amount * 100)
		}, 0)

		// 转换回元（或其他单位）
		return totalInCents / 100
	})
	const excolumns = [
		{
			title: '支出账号',
			dataIndex: 'accountName',
			width: '15%'
		},
		{
			title: '支出类型',
			width: '10%',
			dataIndex: 'settlementCategory'
		},
		{
			title: '支出时间',
			width: '20%',
			dataIndex: 'payerTime'
		},
		{
			title: '支出金额',
			width: '10%',
			dataIndex: 'amount'
		},

		{
			title: '备注',

			dataIndex: 'remark'
		}
	]

	// 提取产品价格计算逻辑
	const calculateProductPrice = (product) => {
		const number = parseFloat(product.number || 0)
		const unitAmount = parseFloat(product.unitAmount || 0)
		const discountRate = parseFloat(product.discountRate || 0)
		const freightShareAmount = parseFloat(product.freightShareAmount || 0)

		// 计算原价
		const originalPrice = moneyUtils.multiply(unitAmount, number)

		// 计算折扣
		const discountAmount = originalPrice * (discountRate / 100)

		// 计算最终金额（原价 - 折扣 + 运费）
		const finalAmount = originalPrice - discountAmount + freightShareAmount

		return {
			originalPrice,
			discountAmount,
			finalAmount,
			unitCostWithFreight: number > 0 ? finalAmount / number : 0
		}
	}

	// 在组件中统一使用
	const changeProductNumber = (index) => {
		const product = formData.value.productList[index]
		const calculations = calculateProductPrice(product)

		// 更新产品数据
		formData.value.productList[index] = {
			...product,
			amount: calculations.finalAmount,
			unitCostWithFreight: calculations.unitCostWithFreight
		}
	}
	const totalPrice = computed(() => {
		return formData.value.productList
			? formData.value.productList
					.reduce((sum, item) => {
						return sum.plus(new Decimal(item.amount ? item.amount : 0))
					}, new Decimal(0))
					.toNumber()
			: 0
	})

	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '15%'
		},
		{
			title: '数量',
			width: '5%',
			dataIndex: 'number'
		},
		{
			title: '单价',
			width: '10%',
			dataIndex: 'unitAmount'
		},
		{
			title: '优惠率',
			width: '10%',
			dataIndex: 'discountRate'
		},

		{
			title: '运费分摊金额',
			width: '15%',
			dataIndex: 'freightShareAmount'
		},
		{
			title: '含运费单位成本',
			width: '15%',
			dataIndex: 'unitCostWithFreight'
		},
		{
			title: '价格',
			width: '10%',
			dataIndex: 'amount'
		},
		{
			title: '备注',

			dataIndex: 'remark'
		}
	]

	const {
		loading,
		load: onOpen,
		error
	} = useLoading(async (record) => {
		open.value = true
		let recordData = cloneDeep(record)
		formData.value = Object.assign({}, recordData)
		const res = await bizPurchaseOrderApi.bizPurchaseOrderDetail({ id: record.id })

		formData.value.productList = res.bizPurchaseOrderItemList

		formData.value.bizExpenditureRecordList = res.bizExpenditureRecordList
	})

	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = Object.assign({
		productList: [required('采购产品必填')],
		amount: [required('采购金额必填')]
	})
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizPurchaseOrderApi
					.bizPurchaseOrderAuditEdit(formDataParam)
					.then(() => {
						onClose()
						emit('successful')
					})
					.finally(() => {
						submitLoading.value = false
					})
			})
			.catch(() => {})
	}
	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
<style lang="less" scoped>
	.add_table {
		::v-deep(:where(.css-dev-only-do-not-override-19iuou).ant-form-item) {
			margin-bottom: 0;
		}
	}
</style>
