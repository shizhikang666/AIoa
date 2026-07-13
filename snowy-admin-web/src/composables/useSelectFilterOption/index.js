import { Segment, useDefault } from 'segmentit'

const segmentit = useDefault(new Segment())

//精确匹配
export function useSelectFilterOption() {
	return (input, option) => {
		return option.label.toLowerCase().indexOf(input.toLowerCase()) >= 0
	}
}

//分词匹配
export function useLikeSelectFilterOption() {
	let inputValue = ''
	const filterOption = (input, option) => {
		inputValue = input
		const inputTokens = segmentit.doSegment(input.toLowerCase(), { simple: true })
		const labelTokens = segmentit.doSegment(option.label.toLowerCase(), { simple: true })
		let matchScore = 0
		inputTokens.forEach((inputToken) => {
			labelTokens.forEach((labelToken, index) => {
				if (labelToken.includes(inputToken)) {
					// 基础分数
					matchScore += 1
					// 如果匹配的 token 在文本开头，增加分数
					if (index === 0) {
						matchScore += 1
					}
					// 如果完全匹配，增加分数
					if (labelToken === inputToken) {
						matchScore += 1
					}
				}
			})
		})

		return matchScore || (option.label.toLowerCase().indexOf(input.toLowerCase()) >= 0 ? 100 : 0)
	}

	const filterAndSortOptions = (a, b) => {
		return filterOption(inputValue, a) > filterOption(inputValue, b)
	}

	return {
		filterOption,
		filterAndSortOptions
	}
}
