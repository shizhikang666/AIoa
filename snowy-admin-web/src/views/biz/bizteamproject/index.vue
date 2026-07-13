<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker v-model:value="searchFormState.createTime" value-format="YYYY-MM-DD HH:mm:ss" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
				</a-col>
			</a-row>
		</a-form>
		<a-row>
			<a-space>
				<a-button type="primary" @click="formRef.onOpen()">
					<template #icon>
						<plus-outlined />
					</template>
					新增
				</a-button>
			</a-space>
		</a-row>
		<br />
		<a-row v-if="error">
			<error-result @reload="refuel"></error-result>
		</a-row>
		<a-row justify="space-around" v-else-if="!loading && pageData.records.length === 0">
			<a-empty>
				<template #description>
					<span>
						你好，
						<a @click="formRef.onOpen()">当前没有团队协作项目，点击新建</a>
					</span>
				</template>
				<a-button type="primary" @click="formRef.onOpen()">新增</a-button>
			</a-empty>
		</a-row>

		<div style="min-height: 200px" v-else>
			<div
				v-if="loading"
				style="display: flex; height: 200px; width: 100%; justify-content: center; align-items: center"
			>
				<a-spin></a-spin>
			</div>
			<a-space v-else direction="vertical">
				<a-flex wrap="wrap" gap="middle" align="center">
					<a-card
						@click="gotoDetails(item)"
						:title="item.name"
						v-for="(item, i) in pageData.records"
						hoverable
						style="width: 300px"
					>
						<p>{{ item.description }}</p>
						<a-card-meta>
							<template #description>{{ item.createTime }}</template>
						</a-card-meta>
					</a-card>
				</a-flex>
			</a-space>
		</div>
		<br />
		<a-pagination
			v-model:pageSize="pageSize"
			show-size-changer
			@showSizeChange="onShowSizeChange"
			v-model:current="current"
			:total="total"
		/>
		<Form ref="formRef" @successful="refuel" />
	</a-card>
</template>

<script setup name="bizteamproject">
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import bizTeamProjectApi from '@/api/biz/bizTeamProjectApi'
	import { useLoading } from '@/composables/useLoading'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import { useRouter } from 'vue-router'

	const router = useRouter()
	const gotoDetails = (item) => {
		router.push({ path: `/biz/bizteamprojectdetails`, query: { id: item.id } })
	}
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const current = ref(1)
	const total = ref(0)
	const pageSize = ref(10)
	const onShowSizeChange = (v) => {
		pageSize.value = v
	}

	const pageData = ref({
		records: []
	})

	// 列表选择配置
	const {
		load: loadData,
		loading,
		error
	} = useLoading(async (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// completionTime范围查询条件重载
		if (searchFormParam.completionTime) {
			searchFormParam.startCompletionTime = searchFormParam.completionTime[0]
			searchFormParam.endCompletionTime = searchFormParam.completionTime[1]
			delete searchFormParam.completionTime
		}
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		pageData.value = await bizTeamProjectApi
			.bizTeamProjectPage(Object.assign(parameter, searchFormParam))
			.then((data) => {
				pageData.value = data
				current.value = data.current
				total.value = data.total
				pageSize.value = data.size
				return data
			})
	})

	// 重置
	const reset = () => {
		searchFormState.value = {}
		loadData({
			current: current.value,
			pageSize: pageSize.value
		})
	}
	// 删除
	const deleteBizTeamProject = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizTeamProjectApi.bizTeamProjectDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchBizTeamProject = (params) => {
		bizTeamProjectApi.bizTeamProjectDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}

	const refuel = async () => {
		loadData({
			current: current.value,
			pageSize: pageSize.value
		})
	}

	onActivated(() => {
		refuel()
	})

	watchEffect(() => {
		loadData({
			current: current.value,
			pageSize: pageSize.value
		})
	})
</script>
