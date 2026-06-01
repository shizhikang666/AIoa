<template>
	<a-popover trigger="click" @openChange="onOpen" placement="bottomRight">
		<template #content>
			<a-space direction="vertical">
				<a-segmented v-model:value="activeKey" :options="data">
					<template #label="{ payload, value }">
						<template v-if="value === 'task'">
							<a-badge dot :count="taskCount">
								{{ payload.title }}
							</a-badge>
						</template>
						<template v-else>
							<a-badge dot :count="messageCount">
								{{ payload.title }}
							</a-badge>
						</template>
					</template>
				</a-segmented>
				<task-item ref="taskRef" v-if="activeKey === 'task'"></task-item>
				<message-item ref="messageRef" v-else></message-item>
			</a-space>
		</template>
		<div class="message panel-item">
			<a-badge dot :count="taskCount + messageCount">
				<BellOutlined />
			</a-badge>
		</div>
	</a-popover>
</template>
<script name="panel-message" setup>
	import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue'
	import BizTaskApi from '@/api/biz/bizTaskApi'
	import TaskItem from '@/layout/components/panel-message/taskItem.vue'
	import tool from '@/utils/tool'
	import sysConfig from '@/config'
	import { EventSourcePolyfill } from 'event-source-polyfill'
	import { message } from 'ant-design-vue'
	import MessageItem from '@/layout/components/panel-message/messageItem.vue'
	import IndexApi from '@/api/sys/indexApi'

	const data = reactive([
		{
			value: 'task',
			payload: {
				title: '我的任务'
			}
		},
		{
			value: 'message',
			payload: {
				title: '系统消息'
			}
		}
	])
	const activeKey = ref(data[0].value)
	const taskCount = ref(0)
	const messageCount = ref(0)
	const taskRef = useTemplateRef('taskRef')
	const messageRef = useTemplateRef('messageRef')

	const loadTaskCount = async () => {
		taskCount.value = await BizTaskApi.bizTaskCount()
	}
	const loadMessageCount = async () => {
		const list = await IndexApi.indexMessageList()
		messageCount.value = list.length
	}

	const onOpen = async (value) => {
		if (value) {
			await nextTick()
			if (activeKey.value === 'task') {
				taskRef.value.loadData()
				await loadTaskCount()
			}
			if (activeKey.value === 'message') {
				await loadMessageCount()
				messageRef.value.loadData()
			}
		}
	}
	onMounted(() => {
		createSseConnect()
	})

	const loadInit = async () => {
		loadTaskCount()
		loadMessageCount()
	}

	// 创建sse连接
	const createSseConnect = () => {
		if (window.EventSource) {
			let clientId = tool.data.get('CLIENTID') ? tool.data.get('CLIENTID') : ''
			let url = sysConfig.API_URL + `/dev/message/createSseConnect?clientId=${clientId}`
			const connect = () => {
				let source = new EventSourcePolyfill(url, {
					headers: { token: tool.data.get('TOKEN') },
					heartbeatTimeout: 300000
				})
				// 监听打开事件
				source.addEventListener('open', (e) => {
					loadInit()
					console.log('SSE 连接已建立')
				})
				// 监听消息事件
				source.addEventListener('message', (e) => {
					const result = JSON.parse(e.data)
					const { code, data } = result
					if (code === 200) {
						if (data === 'FlushProcessNotice') {
							loadTaskCount()
						}
						if (data === 'FlushMessageNotice') {
							loadMessageCount()
						}
					} else if (code === 0) {
						loadInit()
						// 初次建立连接，客户端id储存本地
						tool.data.set('CLIENTID', data)
					}
				})

				// 监听错误事件
				source.addEventListener('error', (e) => {
					console.error('发生错误，消息实时获取已断开与服务器的连接')
					source.close()

					// 5秒后尝试重连
					setTimeout(() => {
						console.log('尝试重新连接...')
						connect()
					}, 5000)
				})
			}

			// 初始连接
			connect()
		} else {
			message.warning('该浏览器不支持消息功能')
		}
	}
</script>
<style scoped></style>
