<template>
	<!--	支出表单分类-->
	<a-comment>
		<template #avatar>
			<a-avatar :src="userInfo.avatar" :alt="userInfo.name" />
		</template>
		<template #content>
			<a-form-item>
				<a-textarea v-model:value="form.comment" placeholder="审批意见" :rows="4" />
			</a-form-item>
			<a-form-item>
				<a-space>
					<a-button v-if="hasPerm([premKey])" :loading="submitting" type="primary" @click="openForm"> 同意 </a-button>
					<a-button @click="submit(false)" :loading="submitting" danger type="primary"> 拒绝</a-button>
				</a-space>
			</a-form-item>
		</template>
	</a-comment>
	<a-modal v-model:open="open" @ok="submit(true)" title="确认">
		<a-form ref="formRef" :model="form" :rules="formRules" layout="vertical">
			<a-form-item label="结算账户：" name="account">
				<a-select
					:filter-option="filterOption"
					:filter-sort="filterAndSortOptions"
					placeholder="请选择结算账户"
					show-search
					v-model:value="form.accountId"
					:options="accountList"
				></a-select>
			</a-form-item>
			<a-form-item label="结算类型：" name="settlementCategory">
				<a-select
					placeholder="选择结算类型"
					v-model:value="form.settlementCategory"
					:options="settlementCategoryList"
				></a-select>
			</a-form-item>
			<template v-for="item in formItems">
				<a-form-item
					:key="item.label"
					v-if="form.settlementCategory === item.category"
					:label="item.label"
					:name="item.name"
				>
					<a-typography-link :type="form.objectId ? '' : 'danger'" @click="item.onClick">
						{{ getLinkText }}
					</a-typography-link>
				</a-form-item>
			</template>
			<a-form-item label="结算日期：" name="payerTime">
				<a-date-picker v-model:value="form.payerTime" value-format="YYYY-MM-DD HH:mm:ss" show-time></a-date-picker>
			</a-form-item>
		</a-form>
	</a-modal>
</template>
<script setup lang="js">
	import { computed } from 'vue'
	import tool from '@/utils/tool'
	import bizTaskApi from '@/api/biz/bizTaskApi'
	import { cloneDeep } from 'lodash-es'
	import SettlementAccountApi from '@/api/biz/settlementAccountApi'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { required } from '@/utils/formRules'
	import { useLikeSelectFilterOption, useSelectFilterOption } from '@/composables/useSelectFilterOption'
	import { formatDate } from '@vueuse/core'
	import { useSettlementAccount } from '@/composables/useSettlementAccount'
	import { App } from 'ant-design-vue'
	import { useLoading } from '@/composables/useLoading'

	const { modal } = App.useApp()
	const { filterOption, filterAndSortOptions } = useLikeSelectFilterOption()
	const userInfo = tool.data.get('USER_INFO')
	const submitting = ref(false)
	const props = defineProps({
		instanceId: {
			type: String,
			required: true
		},
		taskDetail: {
			type: Object,
			required: true
		}
	})
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	const premKey = computed(() => {
		const { processKey, category } = props.taskDetail
		return processKey + '-' + category
	})
	const accountList = ref([])
	const settlementCategoryList = tool.dictListByPath(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY'])

	const { openProjectSelect, openProcureFlowSelect, openCollectionReceipt, openReturnOrder } = useSettlementAccount()

	const config = {
		modal,
		onOk: (value) => {
			form.value.objectId = value.id
		}
	}

	// 表单项配置
	const formItems = ref([
		{
			category: 'GOODS_EXPENDITURE',
			label: '采购单：',
			name: 'objectId',
			onClick: openProcureFlowSelect(config)
		},
		{
			category: 'CUSTOMER_REBATE',
			label: '项目成交单：',
			name: 'objectId',
			onClick: openProjectSelect(config)
		},
		{
			category: 'repayment',
			label: '代收款单：',
			name: 'objectId',
			onClick: openCollectionReceipt(config)
		},
		{
			category: 'ReturnAndRefund',
			label: '退货单：',
			name: 'objectId',
			onClick: openReturnOrder(config)
		}
	])

	const open = ref(false)

	const hasRequireObjectId = computed(() => {
		return formItems.value.find((value) => value.category === form.value.settlementCategory)
	})

	const formRules = computed(() => {
		const baseRule = {
			payerTime: [required('打款时间')],
			settlementCategory: [required('结算分类不能为空')],
			accountId: [required('收款账户不能为空')]
		}

		if (hasRequireObjectId.value) {
			baseRule.objectId = [required('单号不能为空！')]
		}
		return baseRule
	})

	// 计算属性：动态生成链接文本
	const getLinkText = computed(() => {
		return form.value.objectId ? form.value.objectId : '请选择单号'
	})
	const form = ref({
		comment: '',
		approval: '',
		accountId: '',
		settlementCategory: '',
		payerTime: '',
		objectId: ''
	})
	Object.keys(form.value).map((key) => {
		form.value[key] = props.taskDetail.variables[key]
	})
	SettlementAccountApi.settlementAccountList().then((res) => {
		accountList.value = res.map((v) => {
			return {
				label: v.accountName,
				value: v.id
			}
		})
	})

	watch(
		() => form.value.settlementCategory, // 监听的目标
		(newValue, oldValue) => {
			form.value.objectId = undefined
		},
		{ immediate: true } // 立即执行一次
	)

	const openForm = async () => {
		open.value = true
	}

	const submit = async (flag) => {
		form.value.approval = flag

		if (flag) {
			try {
				await formRef.value.validate()
			} catch (e) {
				return
			}
		}

		try {
			submitting.value = true
			await bizTaskApi.approve({
				id: props.taskDetail.taskId,
				form: cloneDeep(form.value)
			})

			emit('successful')
		} catch (e) {
			console.error(e)
		} finally {
			submitting.value = false
		}
	}

	const { loading, load, error } = useLoading(async () => {
		console.log(props.taskDetail)
		const fields = ['objectId']
		const res = await bizProcessApi.bizVariable({ id: props.instanceId, fields })
		const find = res.find((value) => value.name === 'objectId')

		form.value.objectId = find ? find.value : undefined
	})
	load()
</script>

<style scoped></style>
