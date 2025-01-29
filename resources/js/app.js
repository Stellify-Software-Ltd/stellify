import { createApp, toHandlers } from 'vue/dist/vue.esm-bundler'
import { createPinia, defineStore } from 'pinia'
import { ProductionStore } from './stores/ProductionStore';
import sApp from './components/App.vue'
import sWrapper from './components/Elements/Element.vue'
import sInput from './components/Elements/Input.vue'
import sLoop from './components/Elements/Loop.vue'
import sSvg from './components/Elements/Svg.vue'
import sShape from './components/Elements/Shape.vue'
import sForm from './components/Elements/Form.vue'
import sEmbed from './components/Elements/Embed.vue'
import sFreestyle from './components/Elements/Freestyle.vue'
import sTransition from './components/Elements/Transition.vue'
import sDate from './components/Elements/Date.vue'

const pinia = createPinia()

const app = createApp({
    data() {
        return {
            code: {},
			store: ProductionStore()
        }
    },
	async beforeMount() {
        if (this.store.page.cacheType == 'browser' && sessionStorage[this.store.page.slug]) {
            this.store.content = JSON.parse(sessionStorage[this.store.page.slug]);
        }
        if (typeof this.store.page.beforeMount != 'undefined' && this.store.page.beforeMount.length) {
            this.store.page.beforeMount.forEach((method) => {
                this.runMethod(method);
            });
        }
    },
    mounted() {
        if (Object.keys(this.store.content).length > 0 && this.store.page.cacheType == 'browser') {
            sessionStorage.setItem(this.store.page.slug, JSON.stringify(this.store.content));
        }
        Object.keys(window).forEach( key => {
            if (typeof window[key] == 'number') {
                this.store.variables[key] = window[key];
            }
            if (key == 'navigation') {
                Object.keys(Object.getPrototypeOf(window[key])).forEach( navKey => {
                    if (typeof window[key][navKey] == 'boolean') {
                        this.store.variables[navKey] = window[key][navKey];
                    }
                });
            }
        });
          //handle resize
        window.addEventListener('resize', e => {
            this.store.variables.innerHeight = e.innerHeight;
            this.store.variables.innerWidth = e.innerWidth;
        });
        //handle scroll
        window.addEventListener('scroll', e => {
            this.store.variables.scrollX = e.scrollX;
            this.store.variables.scrollY = e.scrollX;
        });
        //observed elements
        const intersectionObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.intersectionRatio > 0) {
                    entry.target.classList.add(this.store.content[entry.target.id].enabledClasses);
                }
                observer.unobserve(entry.target);
            });
        });
        const elements = [...document.querySelectorAll('.observe')];
        elements.forEach((element) => intersectionObserver.observe(element));
        if (typeof this.store.page.mounted != 'undefined' && this.store.page.mounted.length) {
            this.store.page.mounted.forEach((method) => {
                this.runMethod(method);
            });
        }
    },
    beforeUnmount() {
        if (typeof this.store.page.beforeUnmount != 'undefined' && this.store.page.beforeUnmount.length) {
            this.store.page.beforeUnmount.forEach((method) => {
                this.runMethod(method);
            });
        }
    },
    unmounted() {
        if (typeof this.store.page.unmounted != 'undefined' && this.store.page.unmounted.length) {
            this.store.page.unmounted.forEach((method) => {
                this.runMethod(method);
            });
        }
    }
})
.use(pinia)
.component('s-app', sApp)
.component('s-wrapper', sWrapper)
.component('s-input', sInput)
.component('s-loop', sLoop)
.component('s-svg', sSvg)
.component('s-embed', sEmbed)
.component('s-shape', sShape)
.component('s-transition', sTransition)
.component('s-form', sForm)
.component('s-freestyle', sFreestyle)
.mount('#stellify')