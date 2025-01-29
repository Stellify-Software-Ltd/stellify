<template>
    <form
        :action="(store.content[uuid].action ? store.content[uuid].action : '')" 
        :method="store.content[uuid].method" 
        :enctype="store.content[uuid].enctype" 
        :id="store.content[uuid].id"
        :class="[classes]">
        <input v-if="store.content[uuid].method == 'POST'" type="hidden" name="_token" :value="csrf">
        <component
            v-for="(uuid, index) in store.content[uuid].data" 
            :key="index"
            :is="store.content[uuid].type"
            :uuid="uuid">
        </component>
    </form>
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
            }
        },
        computed: {
            csrf() {
                return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            },
            classes() {
                return this.store.content[this.uuid].classes;
            }
        }
    }
</script>