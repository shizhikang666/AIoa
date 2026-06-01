<template>
	<xn-form-container
		title="借款还款"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
		:bodyStyle="{ paddingTop: 0 }"
	>
		<a-tabs v-model:activeKey="activeKey">
			<a-tab-pane tab="还款信息" key="baseInfo" >
				<a-form   ref="formRef"  :model="formData" :rules="formRules"   layout="vertical">
					<template v-for="(item,index) in formData.formDataList">
						<a-descriptions :column="2" size="small" bordered>
							<a-descriptions-item label="单号">{{item.id}}</a-descriptions-item>
							<a-descriptions-item label="总借款">{{item.loanAmount}}</a-descriptions-item>
							<a-descriptions-item label="已还款">
								{{item.settlementAmount}}
							</a-descriptions-item>
							<a-descriptions-item label="剩余未还款">
								{{item.maxAmount}}
							</a-descriptions-item>
							<a-descriptions-item label="备注">{{item.remark}}</a-descriptions-item>
							<a-descriptions-item label="还款金额">
								<a-form-item label="" 	:name="['formDataList', index, 'amount']"  :rules="[{ required: true, message: '请输入还款金额' }]">
									<XnCurrencyInput :max="item.maxAmount" v-model:value="formData.formDataList[index].amount" placeholder="请输入还款金额" allow-clear />
								</a-form-item>
							</a-descriptions-item>
						</a-descriptions>
						<br />
					</template>

				</a-form>
			</a-tab-pane>
			<a-tab-pane tab="还款账户信息" key="accountInfo">
				<a-form   ref="accountFormRef"  :model="formData" :rules="formRules"   layout="vertical">
					<a-form-item label="打款人：" name="payer">
						<a-input  placeholder="打款人" v-model:value="formData.payer"

						></a-input>
					</a-form-item>
					<a-form-item label="收款账户：" name="accountId">
						<a-select show-search :filter-option="filterOption" placeholder="请选择收款账户" v-model:value="formData.accountId" :options="accountList"></a-select>
					</a-form-item>
					<a-form-item label="收款日期：" name="payerTime">
						<a-date-picker v-model:value="formData.payerTime" value-format="YYYY-MM-DD HH:mm:ss" show-time></a-date-picker>
					</a-form-item>

					<a-form-item label="备注：" name="remark">
						<a-textarea v-model:value="formData.remark" placeholder="请输入备注" allow-clear />
					</a-form-item>

				</a-form>
			</a-tab-pane>
		</a-tabs>




		<template #footer>
			<a-row justify="end">
				<a-space>
					<a-typography-title :level="5">共计：{{totalAmount}}</a-typography-title>
					<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
					<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
				</a-space>
			</a-row>
		</template>
	</xn-form-container>
</template>

<script setup name="bizDebitNoteForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizDebitNoteApi from '@/api/biz/bizDebitNoteApi'
	import {Decimal} from "decimal.js";
	import SettlementAccountApi from "@/api/biz/settlementAccountApi";
	import  { useSelectFilterOption } from  '@/composables/useSelectFilterOption/index'
	import {useTemplateRef} from "vue";
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	const accountFormRef = useTemplateRef('accountFormRef');
	// 表单数据
	const formData = ref({
		formDataList:[],
	})
	const activeKey = ref('baseInfo');
	const submitLoading = ref(false)


	const filterOption = useSelectFilterOption();

	const  totalAmount = computed(()=>{
	return 	formData.value.formDataList.reduce((previousValue,currentValue)=>{
				return 	previousValue.add(new Decimal(currentValue.amount?currentValue.amount:0))
		},new Decimal(0))


	})
	const  accountList = ref([]);
	SettlementAccountApi.settlementAccountList().then((res) => {
		accountList.value = res.map((v) => {
			return {
				label: v.accountName,
				value: v.id
			}
		})
	})
	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		activeKey.value = 'baseInfo'
		formData.value.formDataList = record.records.map(v=>{
			const	loanAmount  = v.amount;
			const settlementAmount = v.settlementAmount
			 const amount = new Decimal(v.amount).sub(new Decimal(v.settlementAmount)).toNumber();
			 const maxAmount = amount;
			return {
				...v,
				amount,
				maxAmount,
				loanAmount,
				settlementAmount
			}

		})


	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {
			formDataList:[],
		}
		open.value = false
	}
	// 默认要校验的
	const formRules = {
		payer:[required('收款人')],
		accountId: [required('请选择收款账户')],
		payerTime: [required('请选择收款日期')],
		remark:[required("请输入备注")]
	}
	// 验证并提交数据
	const onSubmit = async  () => {

		try{
			await  	formRef.value.validate()
		}catch(error){
			activeKey.value = 'baseInfo'
			return
		}

		try {
			await  accountFormRef.value.validate();
		}catch(error){
			activeKey.value = 'accountInfo'
			return
		}
		submitLoading.value = true
		try {
			const formDataParam = cloneDeep(formData.value)
			formDataParam.items = formDataParam.formDataList
			delete  formDataParam.formDataList;
			await  bizDebitNoteApi.batchRepayment(formDataParam)
			onClose()
			emit('successful')
		}finally {
			submitLoading.value = false
		}


	}
	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
