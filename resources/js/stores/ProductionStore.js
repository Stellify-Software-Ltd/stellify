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
            let target = null;
            if (typeof this.content[caller].relativeTarget != 'undefined') {
                target = this.resolveRelativeTarget(caller);
            } else if (typeof this.content[caller].target != 'undefined') {
                target = this.content[caller].target;
            }
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
                                    if (this.clauses[clause].type == 'string') {
                                        expression += "'" + this.clauses[clause].value + "'";
                                    } else if (target && this.clauses[clause].type == 'variable' && this.clauses[clause].name == 'target') {
                                        expression += JSON.stringify(target);
                                    } else if (target && this.clauses[clause].type == 'object' && (this.clauses[clause].name == 'data' || this.clauses[clause].name == 'variables')) {
                                        expression += "this.variables";
                                    } else if (target && this.clauses[clause].type == 'object' && this.clauses[clause].name == 'elements') {
                                        expression += "this.content";
                                    } else if (target && this.clauses[clause].type == 'object' && this.clauses[clause].name == 'route') {
                                        expression += "this.page";
                                    } else if (target && this.clauses[clause].type == 'object' && this.clauses[clause].name == 'target') {
                                        expression += "this.content['" + target + "']";
                                    } else if (caller && this.clauses[clause].type == 'object' && this.clauses[clause].name == 'caller') {
                                        expression += "this.content['" + caller + "']";
                                    } else if (this.clauses[clause].type == 'number' || this.clauses[clause].type == 'boolean') {
                                        expression += this.clauses[clause].value;
                                    } else if (this.clauses[clause].type == 'method') {
                                        expression += this.clauses[clause].name;
                                        if (this.clauses[clause].parameters) {
                                            expression += '(';
                                            this.clauses[clause].parameters.forEach((parameter, index) => {
                                                if (parameter.type == 'string') {
                                                    expression += "'" + parameter.value + "'";
                                                } else if (parameter.type == 'number' || parameter.type == 'boolean') {
                                                    expression += parameter.value;
                                                } else if (parameter.type == 'variable') {
                                                    expression += parameter.name;
                                                }
                                                if (index < this.clauses[clause].parameters.length - 1) {
                                                    expression += ', ';
                                                }
                                            });
                                            expression += ')';
                                        }
                                    } else {
                                        expression += this.clauses[clause].name;
                                    }
                                }
                            }
                        });
                    }
                });
                try {
                    //console.log('expression', expression, eval(expression));
                    return eval(expression);
                } catch (error) {
                    console.error('Stellify error: ' +  error);
                    console.log('Code: ' + expression);
                }
            } else {
                console.error('Stellify error: Method not found, have you removed the method and reinstated it? If so, you must update the reference.');
            }
        }
	}
})