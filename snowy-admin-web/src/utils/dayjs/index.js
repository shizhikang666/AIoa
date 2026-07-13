import relativeTime from 'dayjs/plugin/relativeTime'
import dayjs from 'dayjs'
import zhCn from 'dayjs/locale/zh-cn'
// 设置中文显示
dayjs.locale(zhCn)
dayjs.extend(relativeTime)

export default dayjs
