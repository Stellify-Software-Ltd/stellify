<template>
    <component 
		:is="computedTag"
		:class="[classes]"
		xmlns="http://www.w3.org/2000/svg"
		xmlns:xlink="http://www.w3.org/1999/xlink"
		:id="store.content[uuid].id"
        :width="store.content[uuid].width"
        :height="store.content[uuid].height"
        :viewBox="store.content[uuid].viewBox"
        :fill="store.content[uuid].fill"
		:stroke="store.content[uuid].stroke"
		:stroke-width="store.content[uuid].strokeWidth"
		:preserveAspectRatio="store.content[uuid].preserveAspectRatio"
        :aria-labelledby="store.content[uuid].name"
        :role="store.content[uuid].presentation">
		<defs v-if="store.content[uuid].gradient">
			<linearGradient id="tw-gradient" x2="1" y2="1">
				<stop offset="0%" stop-color="var(--tw-gradient-from)" />
				<stop offset="50%" stop-color="var(--tw-gradient-via)" />
				<stop offset="100%" stop-color="var(--tw-gradient-to)" />
			</linearGradient>
		</defs>
		<component
			v-for="(uuid, index) in store.content[uuid].data" 
			:key="index"
			:is="store.content[uuid].type"
			:uuid="uuid">
		</component>
		Sorry, your browser does not support inline SVG.
    </component>
</template>

<script>
	import { ProductionStore } from '../../stores/ProductionStore';
    export default {
        data() {
            return {
                store: ProductionStore()
            }
        },
		props: {
            uuid: {
                type: String,
                default: null
            },
            data: {
                type: String,
                default: null
            }
        },
		computed: {
			computedTag() {
                return ['svg', 'symbol'].includes(this.store.content[this.uuid].tag) ? this.store.content[this.uuid].tag : 'svg';
            },
			classes() {
				return this.store.content[this.uuid].classes;
			}
		}
	}
</script>

