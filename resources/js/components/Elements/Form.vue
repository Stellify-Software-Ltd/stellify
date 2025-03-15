<template>
    <form
        :action="(store.content[uuid].action ? store.content[uuid].action : '')" 
        :method="store.content[uuid].method" 
        :enctype="store.content[uuid].enctype" 
        :id="store.content[uuid].id"
        :class="[classes]"
        @submit="onSubmit">
        <input v-if="store.content[uuid].method == 'POST'" type="hidden" name="_token" :value="csrf">
        <component
            v-for="(uuid, index) in store.content[uuid].data" 
            :key="index"
            :is="store.content[uuid].type"
            :uuid="uuid"
            :parent="uuid">
        </component>
    </form>
</template>

<script>
    import { ProductionStore } from '../../stores/ProductionStore';
    import { Container, Validator } from "stellifyjs";
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
        },
        methods: {
            onSubmit(event) {
                if (typeof this.store.content[this.uuid]['onSubmit'] != 'undefined') {
                    this.store.content[this.uuid].value = event.target.value;
                    this.store.runMethod(this.store.content[this.uuid]['onSubmit'], { caller: this.uuid, event: event });
                }
            }
        }
    }
</script>