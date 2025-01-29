<template>
    <transition-group
        :tag="computedTag"
        :class="[classes]"
        :enter-active-class="enterActiveClasses"
        :enter-from-class="enterFromClasses"
        :enter-to-class="enterToClasses"
        :leave-active-class="leaveActiveClasses"
        :leave-from-class="leaveFromClasses"
        :leave-to-class="leaveToClasses">
        <component
            v-for="(uuid, index) in store.content[uuid].data" 
            :key="index"
            :is="store.content[uuid].type"
            :uuid="uuid">
        </component>
    </transition-group>
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
        data() {
            return {
                items: {},
                data: [],
                timer: null,
                currentItem: 0,
                currentTransition: null
            }
        },
        computed: {
            enterFromClasses() {
                if (store.content[this.uuid].enterFromClasses) {
                    return store.content[this.uuid].enterFromClasses.join(' ');
                }
            },
            enterActiveClasses() {
                if (store.content[this.uuid].enterActiveClasses) {
                    return store.content[this.uuid].enterActiveClasses.join(' ');
                }
            },
            enterToClasses() {
                if (store.content[this.uuid].enterToClasses) {
                    return store.content[this.uuid].enterToClasses.join(' ');
                }
            },
            leaveFromClasses() {
                if (store.content[this.uuid].leaveFromClasses) {
                    return store.content[this.uuid].leaveFromClasses.join(' ');
                }
            },
            leaveActiveClasses() {
                if (store.content[this.uuid].leaveActiveClasses) {
                    return store.content[this.uuid].leaveActiveClasses.join(' ');
                }
            },
            leaveToClasses() {
                if (store.content[this.uuid].leaveToClasses) {
                    return store.content[this.uuid].leaveToClasses.join(' ');
                }
            },
            computedTag() {
                return store.content[this.uuid].tag ? store.content[this.uuid].tag : 'div'
            },
            classes() {
                return store.content[this.uuid].classes;
            }
        }
    }
</script>