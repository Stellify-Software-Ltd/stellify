<template>
    <component
        :is="computedTag"
        :style="[styles]"
        :class="[classes]"
        :id="store.content[uuid].id"
        :href="store.content[uuid].href"
        :width="store.content[uuid].width"
        :height="store.content[uuid].height"
        :d="store.content[uuid].d"
        :r="store.content[uuid].r"
        :pathLength="store.content[uuid].pathLength"
        :points="store.content[uuid].points"
        :rx="store.content[uuid].rx"
        :ry="store.content[uuid].ry"
        :cx="store.content[uuid].cx"
        :cy="store.content[uuid].cy"
        :clip-path="store.content[uuid].clipPath"
        :clip-rule="store.content[uuid].clipRule"
        :color="store.content[uuid].color"
        :color-interpolation="store.content[uuid].colorInterpolation"
        :color-rendering="store.content[uuid].colorRendering"
        :cursor="store.content[uuid].cursor"
        :display="store.content[uuid].display"
        :fill="store.content[uuid].fill"
        :fill-opacity="store.content[uuid].fillOpacity"
        :fill-rule="store.content[uuid].fillRule"
        :mask="store.content[uuid].mask"
        :opacity="store.content[uuid].opacity"
        :pointer-events="store.content[uuid].pointerEvents"
        :shape-rendering="store.content[uuid].strokeRendering"
        :stroke="store.content[uuid].stroke"
        :stroke-width="store.content[uuid].strokeWidth"
        :stroke-dasharray="store.content[uuid].strokeDasharray"
        :stroke-dashoffset="store.content[uuid].strokeDashoffset"
        :stroke-linecap="store.content[uuid].strokeLinecap"
        :stroke-linejoin="store.content[uuid].stokeLinejoin"
        :stroke-miterlimit="store.content[uuid].stokeMiterlimit"
        :stroke-opacity="store.content[uuid].strokeOpacity"
        :transform="store.content[uuid].transform"
        :vector-effect="store.content[uuid].vectorEffect"
        :visibility="store.content[uuid].visibility"
        :filter="store.content[uuid].filter">
        <component
            v-for="(uuid, index) in store.content[uuid].data" 
            :key="index"
            :is="store.content[uuid].type"
            :uuid="uuid">
        </component>
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
                return ['path','rect','circle','polygon','use'].includes(this.store.content[this.uuid].tag) ? this.store.content[this.uuid].tag : 'path'
            },
			classes() {
				return this.store.content[this.uuid].classes;
			},
            styles() {
                return {
                    fontFamily: this.store.content[this.uuid].fontFamily,
                    minHeight: this.store.content[this.uuid].minHeight,
                    height: this.store.content[this.uuid].height,
                    backgroundPosition: this.store.content[this.uuid].backgroundPosition,
                    backgroundSize: this.store.content[this.uuid].backgroundSize,
                    backgroundImage: this.store.content[this.uuid].backgroundImage ? 'url(' + this.store.content[this.uuid].backgroundImage + ')' : null
                };
            }
		}
	}
</script>

