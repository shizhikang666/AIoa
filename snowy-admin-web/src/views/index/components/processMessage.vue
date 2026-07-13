<template>
	<a-card title="流程记录" :bordered="false">
		<template #extra>
			<a @click="leaveFor()">更多</a>
		</template>
		<div class="timeline-div">
			<a-list
				size="small"
				class="demo-loadmore-list"
				:loading="initLoading"
				item-layout="horizontal"
				:data-source="list"
			>
				<template #loadMore>
					<div
						v-if="!initLoading && !loading"
						:style="{ textAlign: 'center', marginTop: '12px', height: '32px', lineHeight: '32px' }"
					>
						<a-button v-if="!isLastPage" @click="onLoadMore">加载更多</a-button>
					</div>
				</template>
				<template #renderItem="{ item }">
					<a-list-item :key="item.id">
						<template #actions>
							<a key="list-loadmore-edit">
								<a-tag :color="$TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_state_color', item.status)">
									{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_state', item.status) }}
								</a-tag>
							</a>
						</template>
						<a-skeleton avatar :title="false" :loading="!!item.loading" active>
							<a-list-item-meta>
								<template #title>
									<a @click="openDetails(item)">
										{{ item.headName }}
										<a-tag>
											{{ item.createTime }}
										</a-tag>
										<a-tag color="processing" :bordered="false">
											{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_category', item.category) }}
										</a-tag>
									</a>
								</template>
								<template #description>
									<a @click="openDetails(item)">
										<a-typography-paragraph
											:ellipsis="true"
											:content="item.remark ? item.remark : '未填写'"
											:title="item.remark ? item.remark : '未填写'"
										/>
										<!--										<a-typography-text>{{ item.createTime }}</a-typography-text>-->
									</a>
								</template>
								<template #avatar>
									<a @click="openDetails(item)">
										<a-avatar :src="item.avatar" />
									</a>
								</template>
							</a-list-item-meta>
						</a-skeleton>
					</a-list-item>
				</template>
			</a-list>
		</div>
	</a-card>

	<processDetails ref="processDetailsRef"></processDetails>
</template>
<script setup name="processMessage">
	import router from '@/router'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { onMounted, useTemplateRef } from 'vue'
	import tool from '@/utils/tool'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'

	const processDetailsRef = useTemplateRef('processDetailsRef')
	const userInfo = tool.data.get('USER_INFO')
	const initLoading = ref(true)
	const loading = ref(false)
	const data = ref([])
	const list = ref([])
	const count = 10
	const current = ref(0)
	const totalPage = ref(1)
	const isLastPage = computed(() => {
		return totalPage.value === current.value
	})

	onMounted(() => {
		// 进来后执行查询
		//loadData()
		onLoadMore()
	})
	const openDetails = (item) => {
		processDetailsRef.value.onOpen({ ...item })
	}
	// 是否展示更多按钮
	const isSuperAdmin = () => {
		return userInfo.roleCodeList && userInfo.roleCodeList.toString().indexOf('superAdmin') !== -1
	}
	const isHasAllProcessPermission = () => {
		return hasPerm('biz-index-allprocess')
	}

	const leaveFor = () => {
		let url = isHasAllProcessPermission() ? '/biz/biztask/allprocess' : '/biz/biztask/mystarttask'
		router.replace({
			path: url
		})
	}

	const onLoadMore = async () => {
		loading.value = true
		list.value = data.value.concat([...new Array(count)].map(() => ({ loading: true, name: '', avatar: '' })))
		try {
			const api = isHasAllProcessPermission() ? bizProcessApi.bizProcessAllPage : bizProcessApi.bizProcessPage
			const res = await api({ current: current.value + 1 })
			const newData = data.value.concat(res.records)
			data.value = newData
			list.value = newData
			totalPage.value = res.pages
			current.value = res.current
			nextTick(() => {
				window.dispatchEvent(new Event('resize'))
			})
		} finally {
			initLoading.value = false
			loading.value = false
		}
	}
</script>

<style scoped>
	.timeline-div {
		height: 300px;
		overflow: auto;
	}
</style>
