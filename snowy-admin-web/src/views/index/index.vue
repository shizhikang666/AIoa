<template>
	<a-row :gutter="10">
		<a-col :span="16" :xs="24" :sm="24" :md="24" :lg="16" :xl="16">
			<userInfo class="mb-2" />
			<shortcut class="mb-2" />
			<a-row :gutter="10">
				<a-col :span="12" :xs="24" :sm="24" :md="10" :lg="10" :xl="10">
					<visLog class="mb-2" />
				</a-col>
				<!--				<a-col :span="12" :xs="24" :sm="24" :md="8" :lg="8" :xl="8">-->
				<!--					<opLog class="mb-2" />-->
				<!--				</a-col>-->
				<a-col :span="12" :xs="24" :sm="24" :md="14" :lg="14" :xl="14">
					<process-message></process-message>
				</a-col>
			</a-row>
		</a-col>
		<a-col :span="8" :xs="24" :sm="24" :md="24" :lg="8" :xl="8">
			<schedule class="mb-2" />
			<miniMessage class="mb-2" />
		</a-col>
	</a-row>
</template>

<script setup name="indexHome">
	import UserInfo from './components/userInfo.vue'
	import Shortcut from './components/shortcut.vue'
	import Schedule from './components/schedule.vue'
	import MiniMessage from './components/miniMessage.vue'
	import VisLog from './components/visLog.vue'
	import OpLog from './components/opLog.vue'
	import ProcessMessage from '@/views/index/components/processMessage.vue'
</script>
