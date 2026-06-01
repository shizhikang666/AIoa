<template>
	<a-card>
		<a-flex gap="middle" vertical>
			<a-row>
				<a-typography-title :level="5">仓库采购</a-typography-title>
			</a-row>
			<a-row>
				<a-space>
					<a-button @click="openStartProcureFlowForm" :icon="h(FormOutlined)">采购申请</a-button>
					<a-button @click="startProcureInWarehouseFlowFormRef.onOpen()" :icon="h(FormOutlined)"> 采购入库 </a-button>
					<!--					<a-button disabled :icon="h(FormOutlined)">出库申请</a-button>-->
					<!--					<a-button disabled :icon="h(FormOutlined)">采购退货</a-button>-->
				</a-space>
			</a-row>
			<br />
			<a-row>
				<a-typography-title :level="5">财务结转</a-typography-title>
			</a-row>
			<a-row>
				<a-space>
					<a-button @click="openStartReimbursementFlowForm()" :icon="h(FormOutlined)">报销申请</a-button>
					<a-button @click="startProcessMakePaymentFlowFormRef.onOpen()" :icon="h(FormOutlined)">付款申请 </a-button>
					<a-button @click="openStartPaymentFlowForm()" :icon="h(FormOutlined)">收款申请</a-button>
					<!--					<a-button disabled :icon="h(FormOutlined)" >开票申请</a-button>-->
				</a-space>
			</a-row>
			<br />

			<a-row>
				<a-typography-title :level="5">人事办公</a-typography-title>
			</a-row>
			<a-row>
				<a-space>
					<a-button @click="startAskForLeaveRef.onOpen('leave')" :icon="h(FormOutlined)">请假申请</a-button>
					<a-button @click="startAskForLeaveRef.onOpen('GoOut')" :icon="h(FormOutlined)">出差/外出申请</a-button>
					<!--					<a-button disabled :icon="h(FormOutlined)">出差申请</a-button>-->
					<!--					<a-button disabled :icon="h(FormOutlined)">补卡申请</a-button>-->
				</a-space>
			</a-row>
		</a-flex>
	</a-card>
	<startProcureFlowForm ref="startProcureFlowFormRef"></startProcureFlowForm>
	<startReimbursementFlowForm ref="startReimbursementFlowFormRef"></startReimbursementFlowForm>
	<startPaymentFlowForm ref="startPaymentFlowFormRef"></startPaymentFlowForm>
	<startProcureInWarehouseFlowForm ref="startProcureInWarehouseFlowFormRef"></startProcureInWarehouseFlowForm>
	<startProcessMakePaymentFlowForm ref="startProcessMakePaymentFlowFormRef"></startProcessMakePaymentFlowForm>
	<startAskForLeave ref="startAskForLeaveRef"></startAskForLeave>
</template>
<script setup name="processList">
	import { h, useTemplateRef } from 'vue'
	import { FormOutlined, SearchOutlined } from '@ant-design/icons-vue'
	import startProcureFlowForm from '@/views/biz/bizprocess/processForm/procure/startProcureFlowForm.vue'
	import startReimbursementFlowForm from '@/views/biz/bizprocess/processForm/payment/startReimbursementFlowForm.vue'
	import startProcessMakePaymentFlowForm from '@/views/biz/bizprocess/processForm/payment/startProcessMakePaymentFlowForm.vue'
	import startPaymentFlowForm from '@/views/biz/bizprocess/processForm/payment/startPaymentFlowForm.vue'
	import startAskForLeave from '@/views/biz/bizprocess/processForm/personnel/starAskForLeave.vue'
	import startProcureInWarehouseFlowForm from '@/views/biz/bizprocess/processForm/procure/startProcureInWarehouseFlowForm.vue'

	const startPaymentFlowFormRef = useTemplateRef('startPaymentFlowFormRef')
	const startProcureFlowFormRef = useTemplateRef('startProcureFlowFormRef')
	const startReimbursementFlowFormRef = useTemplateRef('startReimbursementFlowFormRef')
	const startProcureInWarehouseFlowFormRef = useTemplateRef('startProcureInWarehouseFlowFormRef')
	const startProcessMakePaymentFlowFormRef = useTemplateRef('startProcessMakePaymentFlowFormRef')
	const startAskForLeaveRef = useTemplateRef('startAskForLeaveRef')
	const openStartProcureFlowForm = () => {
		startProcureFlowFormRef.value.onOpen()
	}
	const openStartPaymentFlowForm = () => {
		startPaymentFlowFormRef.value.onOpen()
	}

	const openStartReimbursementFlowForm = () => {
		startReimbursementFlowFormRef.value.onOpen()
	}
</script>
<style scoped></style>
