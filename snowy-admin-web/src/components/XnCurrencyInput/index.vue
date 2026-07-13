<template>
	<a-input-number
		:value="internalValue"
		@change="(v) => (internalValue = v)"
		:formatter="formatter"
		:disabled="disabled"
		:min="min"
		:max="max"
		:parser="parser"
		:precision="2"
		prefix="￥"
		:placeholder="placeholder"
		:step="0.01"
		style="width: 100%"
	/>
</template>

<script setup name="XnCurrencyInput">
	import { ref, watch } from 'vue'
	import { Form } from 'ant-design-vue'

	const formItemContext = Form.useInjectFormItemContext()
	const props = defineProps({
		modelValue: {
			type: Number,
			default: 0
		},
		disabled: {},
		min: {
			type: [Number],
			default: Infinity
		},
		max: {
			type: [Number],
			default: Infinity
		},
		placeholder: {
			type: String,
			default: () => {
				return ''
			}
		}
	})

	let internalValue = ref(props.modelValue)
	const emit = defineEmits(['update:modelValue'])
	const formatter = (value) => {
		return `${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
	}

	const parser = (value) => {
		return value.replace(/(,*)/g, '')
	}

	watch(internalValue, (newValue) => {
		formItemContext.onFieldChange()
		emit('update:modelValue', parseFloat(newValue))
	})

	watch(
		() => props.modelValue,
		(newValue) => {
			internalValue.value = newValue !== undefined ? newValue : 0
		}
	)
</script>
