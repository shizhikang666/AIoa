
<template>
	<a-list :loading="loading" item-layout="vertical" size="large" :pagination="pagination" :data-source="listData">
		<template #renderItem="{ item }">
			<a-list-item key="item.title">
<!--				<a-list-item-meta :description="item.createUserOrgName">-->
<!--					<template #title>-->
<!--						 {{item.createUserName }}-->
<!--					</template>-->
<!--					<template #avatar><a-avatar :src="item.avatar" /></template>-->
<!--				</a-list-item-meta>		-->
<!--				-->

				<a-comment>
					<template #author><a>{{item.createUserName}}</a></template>
					<template #avatar>
						<a-avatar :src="item.avatar" :alt="item.createUserName" />
					</template>
					<template #content>
						<p>跟进日期：{{item.followUpTime}}</p>
						<p v-html="item.content"></p>

					</template>
					<template #datetime>
						<a-tooltip :title="item.createTime">
							<span>{{ dayjs(item.createTime).fromNow() }}</span>
						</a-tooltip>
					</template>
				</a-comment>


			</a-list-item>
		</template>
	</a-list>
	<a-comment>
		<template #avatar>
			<a-avatar :src="userInfo.avatar" :alt="userInfo.name" />
		</template>
		<template #content>
				<a-form ref="formRef" :model="formData" :rules="formRules" >
					<a-form-item label="跟进时间：" name="followUpTime">
						<a-row :span="24">
								<a-col span="6">
									<a-date-picker v-model:value="formData.followUpTime" value-format="YYYY-MM-DD HH:mm:ss" show-time placeholder="请选择跟进时间" style="width: 100%" />
								</a-col>
						</a-row>
					</a-form-item>
					<a-form-item label="" name="content">
						<a-textarea v-model:value="formData.content" :rows="4" />
					</a-form-item>
					<a-form-item label="">
						<a-button html-type="submit" :loading="submitLoading" type="primary" @click="onSubmit">
							添加跟进记录
						</a-button>
					</a-form-item>

				</a-form>
		</template>
	</a-comment>
</template>
<script setup >
import  {  ref} from  'vue'
import customerFollowUpApi from "@/api/biz/customerFollowUpApi";
import { cloneDeep } from "lodash-es";
import { required } from "@/utils/formRules";
import tool from "@/utils/tool";
const pops= defineProps({
	customerId:{
		type:String,
		required:true
	}
})
const userInfo = tool.data.get('USER_INFO')
const loading = ref(false);
const error = ref(false);
const pagination = {
	onChange: (page) => {
		loadData(page);
	},
	pageSize: 5,
	current: 1,
};
const listData = ref([]);
import dayjs from 'dayjs';
import zhCn  from 'dayjs/locale/zh-cn';


import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

// 设置中文显示
dayjs.locale(zhCn);
const loadData = async(page) => {
	try {
		loading.value= true;
		const res =	await customerFollowUpApi.customerFollowUpPage(Object.assign({
		customerId:pops.customerId,
		size: 5,
		current: page,
		sortField:'createTime',
		sortOrder:'descend'
	}));
		pagination.current=  res.current;
		pagination.total = res.total;
		listData.value = res.records;
	}catch(error) {
		error.value = true;
	}finally {
		loading.value =false;
	}

}
loadData(1);

console.log(userInfo)
const formData = ref({
	followUpTime:'',
	content:'',

})
const formRef = ref();
// 默认要校验的
const formRules = {
	followUpTime: [required('请选择跟进时间')],
	content: [required('请输入跟进内容')],
}
const submitLoading  = ref(false);
// 验证并提交数据
const onSubmit = () => {
	formRef.value
		.validate()
		.then(() => {
			submitLoading.value = true
			const formDataParam = cloneDeep(formData.value)
			customerFollowUpApi
				.customerFollowUpSubmitForm({ ...formDataParam,customerId:pops.customerId }, formDataParam.id)
				.then(() => {
						loadData(pagination.current);
						formRef.value.resetFields();
				})
				.finally(() => {
					submitLoading.value = false
				})
		})
		.catch(() => {})
}
//提交记录



















</script>

<style scoped>

</style>
