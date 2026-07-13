<template>
	<div style="width: 500px">
		<a-skeleton active :loading="loading">
			<div class="flex justify-end">
				<a-checkable-tag
					v-for="(tag, index) in tagsData"
					:key="tag"
					@click="currentTags = tag"
					:checked="tag === currentTags"
				>
					{{ tag }}
				</a-checkable-tag>
			</div>
			<a-list
				:pagination="{
					pageSize: 5
				}"
				size="small"
				:data-source="computedList"
			>
				<template #renderItem="{ item }">
					<a-list-item @click="messageDetail(item)" class="cursor-pointer hover:opacity-75">
						<a-list-item-meta>
							<template #description>
								<span style="font-size: 12px">{{ item.content }} </span>
							</template>
							<template #title>
								<span style="font-size: 12px" :class="item.read ? 'opacity-50' : 'opacity-100'">
									{{ item.category === 'SYS' ? '系统消息' : '业务消息' }}
								</span>
							</template>
							<template #avatar>
								<a-badge dot :count="!item.read ? 1 : 0">
									<a-avatar src="/img/zxlogo.png" />
								</a-badge>
							</template>
						</a-list-item-meta>
						<template #actions>
							<a-tooltip :title="dayjs(item.createTime).format('YYYY-MM-DD HH:mm:ss')">
								<span class="text-12">{{ dayjs(item.createTime).fromNow() }}</span>
							</a-tooltip>
						</template>
					</a-list-item>
				</template>
			</a-list>
		</a-skeleton>
		<xn-form-container title="详情" :width="700" :open="visible" :destroy-on-close="true" @close="onClose">
			<a-form ref="formRef" :model="formData" layout="vertical">
				<a-form-item label="主题：" name="subject">
					<span>{{ formData.subject }}</span>
				</a-form-item>
				<a-form-item label="发送时间：" name="createTime">
					<span>{{ formData.createTime }}</span>
				</a-form-item>
				<a-form-item label="内容：" name="content">
					<span>{{ formData.content }}</span>
				</a-form-item>
			</a-form>
		</xn-form-container>
	</div>
</template>

<script setup name="messageItem">
	import indexApi from '@/api/sys/indexApi'
	import { useLoading } from '@/composables/useLoading'
	import { reactive } from 'vue'
	import userCenterApi from '@/api/sys/userCenterApi'
	import dayjs from '@/utils/dayjs'
	import { useRouter } from 'vue-router'

	const tagsData = reactive(['全部', '已读', '未读'])
	const currentTags = ref('全部')
	const router = useRouter()

	const list = ref([])
	const {
		loading,
		load: loadData,
		error
	} = useLoading(async () => {
		const res = await userCenterApi.userLoginUnreadMessagePage({
			size: 999
		})
		list.value = res.records
	})

	const computedList = computed(() => {
		return list.value.filter((v) => {
			if (currentTags.value === '全部') {
				return true
			}
			if (currentTags.value === '已读') {
				return v.read
			} else {
				return !v.read
			}
		})
	})

	// 点击详情
	const messageDetail = async (message) => {
		const param = {
			id: message.id
		}
		const data = await indexApi.indexMessageDetail(param)
		Object.assign(message, data)
		formData.value = message

		const index = list.value.findIndex((v) => v.id === message.id)

		if (index >= 0) {
			list.value[index].read = true
		}

		if (message.extJson) {
			message.href = JSON.parse(message.extJson).href
		}

		await nextTick(() => {
			// 或者手动触发 blur 事件

			const event = new MouseEvent('mousedown', {
				bubbles: true, // 事件是否冒泡
				cancelable: true, // 事件是否可取消
				view: window // 事件的视图
			})

			// 手动触发点击事件
			document.body.dispatchEvent(event)
		})
		if (message.href) {
			router.push(message.href)
			return
		}

		visible.value = true
	}
	// 以下部分是抽屉的
	const visible = ref(false)
	const formRef = ref()
	const receiveInfoList = ref([])
	const formData = ref({})
	const tableRef = ref()
	const columns = [
		{
			title: '姓名',
			dataIndex: 'receiveUserName'
		},
		{
			title: '是否已读',
			dataIndex: 'read',
			width: 120
		}
	]
	// 关闭抽屉
	const onClose = () => {
		visible.value = false
		formData.value = {}
		receiveInfoList.value = []
		loadData()
	}

	watchEffect(() => {
		loadData()
	})
	// 抛出函数
	defineExpose({
		loadData
	})
</script>

<style scoped></style>
