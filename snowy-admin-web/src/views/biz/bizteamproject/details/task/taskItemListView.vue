<template>
	<div
		v-draggable="[
			list,
			{
				onAdd: onAdd,
				animation: 150,
				ghostClass: 'ghost',
				group: 'people'
			}
		]"
	>
		<div
			@click="stopEvent"
			@mouseover="stopEvent"
			@mouseout="stopEvent"
			@mousemove="stopEvent"
			@mousedown="stopEvent"
			class="col handle2"
			v-for="task in list"
			:key="task.id"
		>
			<a-card
				@click.stop="openDetail(task)"
				:style="{
					opacity: task.status === 'COMPLETE' ? 0.5 : 1
				}"
				size="small"
				hoverable
				style="margin-bottom: 10px"
			>
				<a-space align="start">
					<a-checkbox @click.stop="editTaskStatus(task)" :checked="task.status === 'COMPLETE'"></a-checkbox>
					<a-typography-paragraph style="width: 170px">
						<a-space direction="vertical">
							<a-row>
								<a-tag :color="task.status === 'COMPLETE' ? 'green' : ''">
									<template #icon>
										<check-circle-outlined v-if="task.status === 'COMPLETE'" />
									</template>
									{{ task.status === 'COMPLETE' ? '已完成' : '未完成' }}
								</a-tag>
								<div style="width: 170px">
									<a-progress :percent="task.progress" size="small" />
								</div>
							</a-row>
							<a-row>
								{{ task.contentText }}
							</a-row>
						</a-space>
					</a-typography-paragraph>
					<a-avatar :src="task.createUserAvatar" />
				</a-space>
			</a-card>
		</div>
	</div>
</template>
<script setup name="taskViewList">
	import bizTeamProjectTaskApi from '@/api/biz/bizTeamProjectTaskApi'
	import { vDraggable } from 'vue-draggable-plus'

	const { categoryId } = defineProps({
		categoryId: String
	})

	const emit = defineEmits(['openDetail'])
	const list = defineModel('list')
	const onAdd = () => {
		const map = list.value.filter((task) => {
			return task.teamProjectTaskCategoryId !== categoryId
		})

		map.forEach((task) => {
			bizTeamProjectTaskApi.bizTeamProjectTaskEdit({
				id: task.id,
				teamProjectTaskCategoryId: categoryId
			})

			task.teamProjectTaskCategoryId = categoryId
		})
	}
	const editTaskStatus = async (item) => {
		await bizTeamProjectTaskApi.bizTeamProjectTaskEdit({
			id: item.id,
			status: item.status === 'COMPLETE' ? 'TODO' : 'COMPLETE'
		})

		item.status = item.status === 'COMPLETE' ? 'TODO' : 'COMPLETE'
	}

	const openDetail = (item) => {
		emit('openDetail', item)
	}

	const stopEvent = (event) => {
		event.stopPropagation() // 阻止事件冒泡
	}
</script>

<style scoped></style>
