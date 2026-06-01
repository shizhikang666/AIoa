<template>
	<a-skeleton v-if="!error" active :loading="loading">
		<template  v-for="({bizSaleProjectInvoice,invoiceItems},i) in list" :key="i">

			<a-descriptions size="small" title="发货信息"  bordered >
				<a-descriptions-item label="物流编号" :span="2">{{bizSaleProjectInvoice.logisticsId}}</a-descriptions-item>
				<a-descriptions-item label="物流类型" :span="2">
					{{ $TOOL.dictTypeData('LOGISTICS_CATEGORY',bizSaleProjectInvoice.logisticsCategory) }}
				</a-descriptions-item>
				<a-descriptions-item label="收货人">{{ bizSaleProjectInvoice.consignee }}</a-descriptions-item>
				<a-descriptions-item label="收货地址">{{bizSaleProjectInvoice.address}}</a-descriptions-item>
				<a-descriptions-item label="联系电话">{{ bizSaleProjectInvoice.phone }}</a-descriptions-item>
				<a-descriptions-item label="发货单位">{{ bizSaleProjectInvoice.unit }}</a-descriptions-item>
				<a-descriptions-item label="运费支付方式">
					{{ $TOOL.dictTypeData('FREIGHT_CATEGORY',bizSaleProjectInvoice.freightCategory) }}
				</a-descriptions-item>
				<a-descriptions-item label="发货时间 ">{{ bizSaleProjectInvoice.freightTime }}</a-descriptions-item>
				<a-descriptions-item label="流程编号" >
					<a-typography-link @click="detailRef.onOpen({instanceId:bizSaleProjectInvoice.processId})">
						{{ bizSaleProjectInvoice.processId }}
					</a-typography-link>
				</a-descriptions-item>
				<a-descriptions-item label="运费" >¥{{bizSaleProjectInvoice.freight}}</a-descriptions-item>
				<a-descriptions-item label="备注" :span="3">{{ bizSaleProjectInvoice.reamrk }}</a-descriptions-item>
			</a-descriptions>
			<br />
		</template>


		<a-empty  v-if="list.length === 0"/>

	</a-skeleton>
	<a-result v-else status="500" title="500" sub-title="服务器错误">
		<template #extra>
			<a-button type="primary" @click="loadData">重新加载</a-button>
		</template>
	</a-result>
	<processDetails  ref="detailRef"></processDetails>
</template>

<script setup lang="js" name="projectInvoice">

import  processDetails from "@/views/biz/bizprocess/processDetails/index.vue";
import BizSaleProjectInvoiceApi from "@/api/biz/bizSaleProjectInvoiceApi";
const  prop = defineProps({
	projectId: String,
})
const detailRef = ref()
const loading   =ref(false);
 const  error = ref(false);
 const  list = ref([]);
const  loadData =async  ()=>{
		loading.value = true;
		error.value = false;
	try {
		const res =await  	BizSaleProjectInvoiceApi.bizSaleProjectInvoiceList({
			projectId:prop.projectId
		})
		list.value = res;
	}catch (e) {
		error.value = true;
	}finally {
		loading.value = false;
	}


}
loadData();

const columns = [
	{
		name: 'Name',
		dataIndex: 'name',
		key: 'name',
	},
	{
		title: 'Age',
		dataIndex: 'age',
		key: 'age',
	},
	{
		title: 'Address',
		dataIndex: 'address',
		key: 'address',
	},
	{
		title: 'Tags',
		key: 'tags',
		dataIndex: 'tags',
	},
	{
		title: 'Action',
		key: 'action',
	},
];


</script>


<style scoped>

</style>
