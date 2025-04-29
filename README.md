# WooCommerce Stackable Shipping

Plugin para WordPress/WooCommerce que permite agrupar produtos para cálculo de frete, otimizando as dimensões de produtos empilháveis.

## Descrição

Este plugin permite que os gestores da loja configurem quais produtos podem ser empilhados durante a entrega, modificando automaticamente o cálculo das dimensões para frete.

### Características principais:

- Configuração por produto para definir quais itens podem ser empilhados
- Definição de limites máximos de empilhamento para cada produto
- Cálculo automático de novas dimensões para produtos empilhados
- Suporte a diferentes métodos de envio (Correios, Melhor Envio, Jadlog, etc.)
- Soma correta de pesos e ajuste inteligente de dimensões

## Instalação

1. Faça o upload da pasta `woocommerce-stackable-shipping` para o diretório `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure os produtos empilháveis através da página de edição de produto

## Configuração

### Configurando um produto como empilhável:

1. Vá para WooCommerce > Produtos
2. Edite o produto desejado
3. Na aba "Envio", você encontrará novas opções na seção "Dimensões":
   - **Produto Empilhável** - Marque esta opção para habilitar o empilhamento
   - **Empilhamento Máximo** - Defina quantas unidades deste produto podem ser empilhadas
   - **Incremento de Altura ao Empilhar** - Defina o acréscimo de altura para cada unidade adicional

### Visualizando produtos empilháveis:

O plugin adiciona uma nova página no menu do WooCommerce chamada "Agrupamento de Frete". Nesta página você pode:
- Ver todos os produtos configurados como empilháveis
- Acessar rápido à edição de cada produto
- Visualizar exemplos de como o cálculo de empilhamento funciona

## Como funciona

Quando um cliente adiciona produtos ao carrinho e solicita cálculo de frete, o plugin:

1. Identifica os produtos marcados como empilháveis
2. Agrupa os produtos conforme regras de empilhamento
3. Calcula novas dimensões para cada grupo
4. Envia essas dimensões otimizadas para as transportadoras 
5. O peso permanece inalterado (soma de todos os produtos)

### Exemplo:

- Produto A: 20×30×10 cm
- Empilhável até 3 unidades
- Incremento de altura: 5 cm para cada unidade adicional

Se o cliente comprar 3 unidades do Produto A, ao invés de considerar 3 volumes separados, o sistema utilizará:
- 1 volume com dimensões: 20×30×20 cm (altura base + 2 incrementos)
- Peso: soma dos 3 produtos

## Compatibilidade

- WordPress 5.6+
- WooCommerce 5.0+
- Compatível com os principais plugins de frete: Correios, Melhor Envio, Jadlog, entre outros.

## Suporte

Para relatar problemas ou solicitar novas funcionalidades, por favor abra uma issue no repositório do GitHub ou entre em contato pelo suporte oficial. 