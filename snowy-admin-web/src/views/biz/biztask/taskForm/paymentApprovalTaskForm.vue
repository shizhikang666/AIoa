<template>
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
	<a-modal v-model:open="open" :ok-button-props="{ loading: submitting }" @ok="submit(true)" title="确认">
		<a-form ref="formRef" :model="form" :rules="formRules" layout="vertical">
			<a-form-item label="结算账户：" name="account">
				<a-select
					:filter-option="filterOption"
					:filter-sort="filterAndSortOptions"
					placeholder="请选择结算账户"
					v-model:value="form.accountId"
					:options="accountList"
					show-search
				></a-select>
			</a-form-item>
			<a-form-item label="结算类型：" name="settlementCategory">
				<!--				<a-select-->
				<!--					placeholder="选择结算类型"-->
				<!--					v-model:value="form.settlementCategory"-->
				<!--					:options="settlementCategoryList"-->
				<!--				></a-select>-->

				<a-cascader
					v-model:value="form.settlementCategory"
					:fieldNames="{
						children: 'children',
						label: 'dictLabel',
						value: 'dictValue'
					}"
					:options="settlementCategoryList"
					placeholder="选择结算类型"
					change-on-select
				/>
			</a-form-item>

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

	const { filterOption, filterAndSortOptions } = useLikeSelectFilterOption()
	const userInfo = tool.data.get('USER_INFO')
	const submitting = ref(false)
	const props = defineProps({
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

	const settlementCategoryList = tool.dictTypeList(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'INCOME_CATEGORY'])
	const open = ref(false)
	const formRules = ref({
		payerTime: [required('打款时间')],
		settlementCategory: [required('结算分类不能为空')],
		accountId: [required('收款账户不能为空')]
	})

	const form = ref({
		comment: '',
		approval: '',
		accountId: '',
		settlementCategory: '',
		payerTime: ''
	})

	Object.keys(form.value).map((key) => {
		form.value[key] = props.taskDetail.variables[key]
	})
	form.value.settlementCategory = form.value.settlementCategory.split('/')
	SettlementAccountApi.settlementAccountList().then((res) => {
		accountList.value = res.map((v) => {
			return {
				label: v.accountName,
				value: v.id
			}
		})
	})

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

		const formParam = cloneDeep(form.value)
		formParam.settlementCategory = formParam.settlementCategory.join('/')
		try {
			submitting.value = true
			await bizTaskApi.approve({
				id: props.taskDetail.taskId,
				form: formParam
			})

			emit('successful')
		} catch (e) {
			console.error(e)
		} finally {
			submitting.value = false
		}
	}
</script>

<style scoped></style>
