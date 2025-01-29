<template>
    <span :class="[{'border-2 border-pink-400': typeof this.store.editor != 'undefined' && this.store.editor.guidelines && this.store.editor.currentIndicator == this.uuid}]" class="text-white">
        <span v-for="(slug, clauseIndex) in this.store.statements[uuid].data" :key="clauseIndex">
            <span v-if="this.store.clauses[slug]">
            <span v-if="this.store.clauses[slug].type == 'element' && !edit" :class="element"><span :class="method">elements<span class="text-white">.</span></span><span v-if="typeof this.store.clauses[slug].value != 'undefined'">{{ this.store.namedElements[this.store.clauses[slug].value].name }}</span></span>
            <span v-if="this.store.clauses[slug].type == 'element' && edit"><select v-model="this.store.clauses[slug].value" @click="selectClause(slug)" @change="saveClause" class="rounded-md border-0 bg-white py-1.5 pl-3 pr-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm/6"><option value="">Select an element</option><option v-for="(view, key) in this.store.views" :key="key" :value="view.slug">{{ view.name }}</option></select></span>
            <span v-if="this.store.clauses[slug].type == 'method'" :class="method">{{ this.store.clauses[slug].name }}</span>
            <span v-if="this.store.clauses[slug].type == 'object'" :class="property">{{ this.store.clauses[slug].name }}</span>
            <span v-if="this.store.clauses[slug].type == 'number' && !edit" :class="number">{{ this.store.clauses[slug].value }}</span>
            <span v-if="this.store.clauses[slug].type == 'number' && edit" :class="number"><input type="number" @click="selectClause(slug)" @input="saveClause" v-model="this.store.clauses[slug].value" tabindex="0" class="rounded-md border-0 bg-white py-1.5 pl-3 pr-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm/6" autocomplete="off"></span>
            <span v-if="this.store.clauses[slug].type == 'float'" :class="number">{{ this.store.clauses[slug].value }}</span>
            <span v-if="this.store.clauses[slug].type == 'string' && !edit" :class="string">'{{ this.store.clauses[slug].value }}'</span>
            <span v-if="this.store.clauses[slug].type == 'string' && edit"><input type="text" @click="selectClause(slug)" @input="saveClause" v-model="this.store.clauses[slug].value" tabindex="0" class="rounded-md border-0 bg-white py-1.5 pl-3 pr-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm/6" autocomplete="off"></span>
            <span v-if="this.store.clauses[slug].type == 'boolean' && !edit" :class="key">{{ this.store.clauses[slug].value }}</span>
            <input v-if="this.store.clauses[slug].type == 'boolean' && edit" @click="selectClause(slug)" @input="saveClause" v-model="this.store.clauses[slug].value" class="focus:outline-none focus:border-none focus:shadow-none h-4 w-4 text-blue-600 border-gray-300 rounded" type="checkbox" id="clauseBoolean" autocomplete="off">
            <span v-if="this.store.clauses[slug].type == 'key'" :class="key">{{ this.store.clauses[slug].value }}</span>
            <span v-if="this.store.clauses[slug].type == 'null'" :class="property">NULL</span>
            <span v-if="this.store.clauses[slug].type == 'variable' && !edit" :class="variable">{{ (php ? '$' : '') + this.store.clauses[slug].name }}</span>
            <span v-if="this.store.clauses[slug].type == 'variable' && edit"><input type="text" @click="selectClause(slug)" @input="saveClause" v-model="this.store.clauses[slug].name" tabindex="0" class="rounded-md border-0 bg-white py-1.5 pl-3 pr-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm/6" autocomplete="off"></span>
            <span v-if="this.store.clauses[slug].type == 'class' && php" :class="property">{{ this.store.clauses[slug].name.split("\\")[this.store.clauses[slug].name.split("\\").length - 1] }}</span>
            <span v-if="this.store.clauses[slug].type == 'class' && !php" :class="property">{{ this.store.clauses[slug].name }}</span>
            <span v-if="this.store.clauses[slug].type == 'model'" :class="property">{{ this.store.clauses[slug].name }}</span>
            <span v-if="this.store.clauses[slug].type == 'property' && !edit" :class="property">{{ this.store.clauses[slug].name }}</span>
            <span v-if="this.store.clauses[slug].type == 'property' && edit"><input type="text" @click="selectClause(slug)" @input="saveClause" v-model="this.store.clauses[slug].name" tabindex="0" class="rounded-md border-0 bg-white py-1.5 pl-3 pr-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm/6" autocomplete="off"></span>
            <span v-if="this.store.clauses[slug].type == 'T_IF'" :class="keyword">if&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_OPEN_PARENTHESIS'" :class="operator">(</span>
            <span v-if="this.store.clauses[slug].type == 'T_OPEN_BRACE'" :class="operator">{</span>
            <span v-if="this.store.clauses[slug].type == 'T_CLOSE_BRACE'" :class="operator">}</span>
            <span v-if="this.store.clauses[slug].type == 'T_CLOSE_PARENTHESIS'" :class="operator">)</span>
            <span v-if="this.store.clauses[slug].type == 'T_OPEN_BRACKET'" :class="operator">[</span>
            <span v-if="this.store.clauses[slug].type == 'T_CLOSE_BRACKET'" :class="operator">]</span>
            <span v-if="this.store.clauses[slug].type == 'T_END_LINE'" :class="operator">;</span>
            <span v-if="this.store.clauses[slug].type == 'T_DOUBLE_COLON'" :class="operator">::</span>
            <span v-if="this.store.clauses[slug].type == 'T_DOUBLE_ARROW'" :class="operator">&nbsp;=>&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_EQUALS'" :class="operator">&nbsp;=&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_FUNCTION'" :class="keyword">function&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_PUBLIC'" :class="keyword">public&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_PROTECTED'" :class="keyword">protected&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_PRIVATE'" :class="keyword">private&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_STATIC'" :class="keyword">static&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_STRING'" :class="keyword">(string)&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_ELSE'" :class="keyword">else&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_ELSEIF'" :class="keyword">else if&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_FOREACH'" :class="keyword">foreach&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_FOR'" :class="keyword">for&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_WHILE'" :class="keyword">while&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_DO'" :class="keyword">do&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_THIS'" :class="keyword">$this</span>
            <span v-if="this.store.clauses[slug].type == 'T_RETURN'" :class="keyword">return&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_OBJECT_OPERATOR'" :class="operator">-></span>
            <span v-if="this.store.clauses[slug].type == 'T_NEW'" :class="keyword">new&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'T_AND_EQUAL'" :class="operator"> &= </span>
            <span v-if="this.store.clauses[slug].type == 'T_OR_EQUAL'" :class="operator"> |= </span>
            <span v-if="this.store.clauses[slug].type == 'T_XOR_EQUAL'" :class="operator"> ^= </span>
            <span v-if="this.store.clauses[slug].type == 'T_PLUS_EQUAL'" :class="operator"> += </span>
            <span v-if="this.store.clauses[slug].type == 'T_MINUS_EQUAL'" :class="operator"> -= </span>
            <span v-if="this.store.clauses[slug].type == 'T_MUL_EQUAL'" :class="operator">*=</span>
            <span v-if="this.store.clauses[slug].type == 'T_DIV_EQUAL'" :class="operator">/=</span>
            <span v-if="this.store.clauses[slug].type == 'T_MOD_EQUAL'" :class="operator">%=</span>
            <span v-if="this.store.clauses[slug].type == 'T_COALESCE'" :class="operator">??</span>
            <span v-if="this.store.clauses[slug].type == 'T_SPACESHIP'" :class="operator"><=></span>
            <span v-if="this.store.clauses[slug].type == 'T_IS_EQUAL'" :class="operator">==</span>
            <span v-if="this.store.clauses[slug].type == 'T_IS_NOT'" :class="operator">!</span>
            <span v-if="this.store.clauses[slug].type == 'T_IS_NOT_EQUAL'" :class="operator">!=</span>
            <span v-if="this.store.clauses[slug].type == 'T_IS_IDENTICAL'" :class="operator">===</span>
            <span v-if="this.store.clauses[slug].type == 'T_IS_NOT_IDENTICAL'" :class="operator">!==</span>
            <span v-if="this.store.clauses[slug].type == 'T_GREATER_EQUAL'" :class="operator">>=</span>
            <span v-if="this.store.clauses[slug].type == 'T_LESS_EQUAL'" :class="operator"><=</span>
            <span v-if="this.store.clauses[slug].type == 'T_COMMA'" :class="punctuation">,</span>
            <span v-if="this.store.clauses[slug].type == 'T_CLASS'" :class="keyword">class</span>
            <span v-if="this.store.clauses[slug].type == 'T_EMPTY'" :class="method">empty</span>
            <span v-if="this.store.clauses[slug].type == 'T_ISSET'" :class="method">isset</span>
            <span v-if="this.store.clauses[slug].type == 'T_INSTANCEOF'" :class="keyword">instanceof&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'var'" :class="property">var&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'let'" :class="property">let&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'const'" :class="property">const&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'function'" :class="property">function</span>
            <span v-if="this.store.clauses[slug].type == 'arrow'" :class="keyword">&nbsp;=>&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'if'" :class="keyword">if&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'else'" :class="keyword">&nbsp;else&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'for'" :class="keyword">for&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'while'" :class="keyword">while&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'do'" :class="keyword">do&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'switch'" :class="keyword">switch&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'case'" :class="keyword">case&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'default'" :class="keyword">default&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'return'" :class="keyword">return&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'break'" :class="keyword">break</span>
            <span v-if="this.store.clauses[slug].type == 'continue'" :class="keyword">continue</span>
            <span v-if="this.store.clauses[slug].type == 'throw'" :class="keyword">throw&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'try'" :class="keyword">try&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'catch'" :class="keyword">catch</span>
            <span v-if="this.store.clauses[slug].type == 'finally'" :class="keyword">finally</span>
            <span v-if="this.store.clauses[slug].type == 'extends'" :class="key">&nbsp;extends&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'super'" :class="key">super</span>
            <span v-if="this.store.clauses[slug].type == 'import'" :class="key">import</span>
            <span v-if="this.store.clauses[slug].type == 'export'" :class="key">export</span>
            <span v-if="this.store.clauses[slug].type == 'new'" :class="key">new</span>
            <span v-if="this.store.clauses[slug].type == 'this'" :class="key">this</span>
            <span v-if="this.store.clauses[slug].type == 'in'" :class="key">in</span>
            <span v-if="this.store.clauses[slug].type == 'instanceof'" :class="key">instanceof</span>
            <span v-if="this.store.clauses[slug].type == 'typeof'" :class="key">typeof</span>
            <span v-if="this.store.clauses[slug].type == 'void'" :class="key">void</span>
            <span v-if="this.store.clauses[slug].type == 'delete'" :class="key">delete</span>
            <span v-if="this.store.clauses[slug].type == 'async'" :class="key">async</span>
            <span v-if="this.store.clauses[slug].type == 'await'" :class="key">await</span>
            <span v-if="this.store.clauses[slug].type == 'yield'" :class="key">yield</span>
            <span v-if="this.store.clauses[slug].type == 'with'" :class="key">with</span>
            <span v-if="this.store.clauses[slug].type == 'true'" :class="key">true</span>
            <span v-if="this.store.clauses[slug].type == 'false'" :class="key">false</span>
            <span v-if="this.store.clauses[slug].type == 'undefined'" :class="key">undefined</span>
            <span v-if="this.store.clauses[slug].type == 'nan'" :class="key">NaN</span>
            <span v-if="this.store.clauses[slug].type == 'infinity'" :class="key">Infinity</span>
            <span v-if="this.store.clauses[slug].type == 'addition'" :class="operator"> + </span>
            <span v-if="this.store.clauses[slug].type == 'subtraction'" :class="operator"> - </span>
            <span v-if="this.store.clauses[slug].type == 'multiplication'" :class="operator"> * </span>
            <span v-if="this.store.clauses[slug].type == 'division'" :class="operator"> / </span>
            <span v-if="this.store.clauses[slug].type == 'modulus'" :class="operator"> % </span>
            <span v-if="this.store.clauses[slug].type == 'increment'" :class="operator">++</span>
            <span v-if="this.store.clauses[slug].type == 'decrement'" :class="operator">--</span>
            <span v-if="this.store.clauses[slug].type == 'assignment'" :class="operator">&nbsp;=&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'addition_assignment'" :class="operator">+=</span>
            <span v-if="this.store.clauses[slug].type == 'subtraction_assignment'" :class="operator">-=</span>
            <span v-if="this.store.clauses[slug].type == 'multiplication_assignment'" :class="operator">*=</span>
            <span v-if="this.store.clauses[slug].type == 'division_assignment'" :class="operator">/=</span>
            <span v-if="this.store.clauses[slug].type == 'modulus_assignment'" :class="operator">%=</span>
            <span v-if="this.store.clauses[slug].type == 'equal'" :class="operator">&nbsp;==&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'strict_equal'" :class="operator">&nbsp;===&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'not_equal'" :class="operator">&nbsp;!=&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'strict_not_equal'" :class="operator">&nbsp;!==&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'greater_than'" :class="operator">></span>
            <span v-if="this.store.clauses[slug].type == 'greater_than_or_equal'" :class="operator">>=</span>
            <span v-if="this.store.clauses[slug].type == 'less_than'" :class="operator"><</span>
            <span v-if="this.store.clauses[slug].type == 'less_than_or_equal'" :class="operator"><=</span>
            <span v-if="this.store.clauses[slug].type == 'logical_and'" :class="operator">&&</span>
            <span v-if="this.store.clauses[slug].type == 'logical_or'" :class="operator">||</span>
            <span v-if="this.store.clauses[slug].type == 'logical_not'" :class="operator">!</span>
            <span v-if="this.store.clauses[slug].type == 'bitwise_and'" :class="operator">&</span>
            <span v-if="this.store.clauses[slug].type == 'bitwise_or'" :class="operator">|</span>
            <span v-if="this.store.clauses[slug].type == 'bitwise_not'" :class="operator">~</span>
            <span v-if="this.store.clauses[slug].type == 'bitwise_xor'" :class="operator">^</span>
            <span v-if="this.store.clauses[slug].type == 'left_shift'" :class="operator"><<</span>
            <span v-if="this.store.clauses[slug].type == 'right_shift'" :class="operator">>></span>
            <span v-if="this.store.clauses[slug].type == 'unsigned_right_shift'" :class="operator">>>></span>
            <span v-if="this.store.clauses[slug].type == 'ternary'" :class="operator">?</span>
            <span v-if="this.store.clauses[slug].type == 'open_parenthesis'" :class="operator">(</span>
            <span v-if="this.store.clauses[slug].type == 'close_parenthesis'" :class="operator">)</span>
            <span v-if="this.store.clauses[slug].type == 'open_bracket'" :class="operator">[</span>
            <span v-if="this.store.clauses[slug].type == 'close_bracket'" :class="operator">]</span>
            <span v-if="this.store.clauses[slug].type == 'open_brace'" :class="operator">{</span>
            <span v-if="this.store.clauses[slug].type == 'close_brace'" :class="operator">}</span>
            <span v-if="this.store.clauses[slug].type == 'semicolon'" :class="operator">;</span>
            <span v-if="this.store.clauses[slug].type == 'colon'" :class="operator">:&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'comma'" :class="operator">,&nbsp;</span>
            <span v-if="this.store.clauses[slug].type == 'period'" :class="operator">.</span>
            <span v-if="this.store.clauses[slug].type == 'start'" :class="operator">`</span>
            <span v-if="this.store.clauses[slug].type == 'end'" :class="operator">`</span>
            <span v-if="this.store.clauses[slug].type == 'placeholder_start'" :class="operator">${</span>
            <span v-if="this.store.clauses[slug].type == 'placeholder_end'" :class="operator">}</span>
            </span>
        </span>
    </span>
</template>

<script>
    import debounce from 'lodash/debounce';
    import { StellifyStore } from '../../stores/StellifyStore';
    export default {
        props: {
            uuid: {
                type: String,
                default: null
            },
            php: {
                type: Boolean,
                default: true
            },
            edit: {
                type: Boolean,
                default: true
            }
        },
        data() {
            return {
                store: StellifyStore(),
                method: 'text-yellow-400',
                keyword: 'text-purple-400',
                key: 'text-blue-400',
                property: 'text-green-400',
                variable: 'text-blue-200',
                string: 'text-orange-400',
                number: 'text-green-400',
                operator: '',
                punctuation: '',
                element: 'text-red-400',
                other: ''
            }
        },
        methods: {
            selectClause(slug) {
                this.store.editor.currentClause = slug;
            },
            saveClause: debounce(function () {
                if (this.store.user.slug && this.store.clauses[this.store.editor.currentClause]) {
                    this.store.editor.loading = true;
                    fetch('/saveClause', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(this.store.clauses[this.store.editor.currentClause]),
                    })
                    .then(response => response.json())
                    .then(response => {
                        if(response.errors) {
                            
                        }
                        this.store.editor.loading = false;
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                    });
                }
            }, 500),
        }
    }
</script> 