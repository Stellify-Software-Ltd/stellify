import { defineStore } from 'pinia';

export const ProductionStore = defineStore('stellifyStore', {
    state : () => (
        {
            content: window.App.content ? window.App.content : {},
            page: window.App.body,
            route: window.App.route,
            project: window.App.project,
            errors: window.App.errors,
            settings: window.App.settings,
            variables: window.App.variables,
            files: window.App.files ? window.App.files : {},
            methods: window.App.methods,
            statements: window.App.statements,
            clauses: window.App.clauses,
            user: window.App.user,
            jsTokens: {
				var: 'var ',
				let: 'let ',
				const: 'const ',
				function: 'function ',
				if: 'if ',
				else: 'else ',
				for: 'for ',
				while: 'while ',
				do: 'do ',
				switch: 'switch ',
				case: 'case ',
				default: 'default ',
				return: 'return ',
                arrow: ' => ',
				break: 'break',
				continue: 'continue',
				throw: 'throw ',
				try: 'try',
				catch: 'catch ',
				finally: 'finally',
				class: 'class ',
				extends: 'extends ',
				super: 'super',
				import: 'import ',
				export: 'export ',
				new: 'new ',
				this: 'this',
				in: 'in ',
				instanceof: 'instanceof ',
				typeof: 'typeof ',
				void: 'void ',
				delete: 'delete ',
				async: 'async ',
				await: 'await ',
				yield: 'yield ',
				with: 'with ',
				null: 'null',
				undefined: '',
				nan: 'NaN',
				infinity: 'Infinity',
				addition: ' + ',
				subtraction: ' - ',
				multiplication: ' * ',
				division: ' / ',
				modulus: '%',
				increment: '++',
				decrement: '--',
				assignment: ' = ',
				addition_assignment: ' += ',
				subtraction_assignment: ' -= ',
				multiplication_assignment: ' *= ',
				division_assignment: ' /= ',
				modulus_assignment: ' %= ',
				equal: ' == ',
				strict_equal: ' === ',
				not_equal: ' != ',
				strict_not_equal: ' !== ',
				greater_than: ' > ',
				greater_than_or_equal: ' >= ',
				less_than: ' < ',
				less_than_or_equal: ' <= ',
				logical_and: ' && ',
				logical_or: ' || ',
				logical_not: '!',
				bitwise_and: ' & ',
				bitwise_or: ' | ',
				bitwise_not: ' ~ ',
				bitwise_xor: ' ^ ',
				left_shift: '<<',
				right_shift: '>>',
				unsigned_right_shift: '>>>',
				ternary: ' ? ',
				open_parenthesis: '(',
				close_parenthesis: ')',
				open_bracket: '[',
				close_bracket: ']',
				open_brace: '{',
				close_brace: '}',
				semicolon: ';',
				colon: ':',
				comma: ',',
				period: '.',
				start: '`',
				end: '`',
				placeholder_start: '${',
				placeholder_end: '}'
			}
		}
    ),
	actions: {
        runMethod(method, { caller, event }) {
            if (this.methods[method] && Object.keys(this.jsTokens).length > 0) {
                let expression = '';
                this.methods[method].data.forEach((statement) => {
                    if (this.statements[statement]) {
                        this.statements[statement].data.forEach((clause) => {
                            if (this.clauses[clause] && this.clauses[clause].type) {
                                if (
                                    typeof this.jsTokens[this.clauses[clause].type] != 'undefined' &&
                                    ['array', 'tuple', 'string', 'number', 'method', 'float', 'integer', 'object', 'property', 'element', 'variable', 'boolean'].includes(this.clauses[clause].type) == false
                                ) {
                                    expression += this.jsTokens[this.clauses[clause].type];
                                } else {
                                    if (this.clauses[clause].type == 'variable' && this.clauses[clause].name == 'value'){
                                        expression += "'" + event.target.value + "'";
                                    } else if (this.clauses[clause].name == 'callerId') {
                                        expression += "'" + caller + "'";
                                    } else if (this.clauses[clause].type == 'object') {
                                        if (this.clauses[clause].name == 'caller') {
                                            expression += "window.App.content['" + caller + "']";
                                        } else {
                                            expression += this.clauses[clause].name;
                                        }
                                    } else if (this.clauses[clause].type == 'element') {
                                        expression += "window.App.content['" + this.clauses[clause].name + "']"
                                    } else if (this.clauses[clause].type == 'string') {
                                        expression += "'" + this.clauses[clause].value + "'";
                                    } else if (this.clauses[clause].type == 'number' || this.clauses[clause].type == 'boolean') {
                                        expression += this.clauses[clause].value;
                                    } else {
                                        expression += this.clauses[clause].name;
                                    }
                                }
                            }
                        });
                    }
                });
                try {
                    return eval(expression);
                } catch (error) {
                    console.error('Stellify error: ' +  error);
                }
            } else {
                console.error('Stellify error: Method not found, have you removed the method and reinstated it? If so, you must update the reference.');
            }
        }
	}
})