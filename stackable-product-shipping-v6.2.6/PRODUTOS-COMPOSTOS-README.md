# Produtos Compostos - Stackable Product Shipping

## Visão Geral

Esta funcionalidade permite criar **Produtos Compostos** que são formados por múltiplos produtos filhos, com cálculo automático de dimensões, peso e integração completa com o sistema de frete CDP (Custom Dimension Products) e SPS (Stackable Product Shipping).

## Características Principais

### 1. **Tipo de Produto Composto**
- Novo tipo de produto: `composed`
- Configuração de produtos filhos com quantidades específicas
- Duas políticas de composição:
  - **Soma de Volumes**: Soma os volumes individuais dos produtos filhos
  - **Caixa Envolvente**: Calcula a menor caixa que engloba todos os produtos

### 2. **Derivação Automática de Dimensões**
- Cálculo automático de peso e dimensões baseado nos produtos filhos
- Suporte a ambas as políticas de composição
- Atualização em tempo real na interface administrativa

### 3. **Integração CDP Completa**
- Validação de volume para produtos compostos
- Geração automática de pacotes de excedente quando o volume ultrapassa os limites CDP
- Cálculo de frete considerando pacotes principais e de excedente

### 4. **Sistema de Múltiplos Pacotes**
- Extensão da classe `CDP_Multi_Packages` para suportar produtos compostos
- Criação de pacotes virtuais para cálculo de frete
- Processamento no sistema de shipping do WooCommerce

### 5. **Regras de Empilhamento**
- Produtos compostos são automaticamente marcados como **não-empilháveis**
- Remoção automática da lista de produtos empilháveis
- Prevenção de conflitos no sistema SPS

### 6. **Exibição Aprimorada no Carrinho**
- Visualização detalhada dos produtos inclusos
- Exibição das dimensões e peso derivados
- Informações sobre pacotes de excedente gerados
- Política de composição utilizada
- Estilos CSS personalizados para melhor UX

## Arquivos Modificados/Criados

### Arquivos Principais
1. **`includes/class-sps-composed-product.php`** - Classe principal dos produtos compostos
2. **`includes/class-cdp-multi-packages.php`** - Extensões para múltiplos pacotes
3. **`stackable-product-shipping.php`** - Integração com sistema de shipping
4. **`assets/css/cdp-styles.css`** - Estilos para exibição no carrinho
5. **`assets/js/sps-composed-admin.js`** - JavaScript para interface administrativa

### Arquivos de Teste
1. **`test-composed-products.php`** - Suite de testes completa
2. **`PRODUTOS-COMPOSTOS-README.md`** - Esta documentação

## Como Usar

### 1. Criando um Produto Composto

1. Acesse **Produtos > Adicionar Novo** no WordPress Admin
2. Na aba **Dados do Produto**, selecione **"Produto Composto"** no dropdown
3. Configure os produtos filhos:
   - Selecione produtos existentes
   - Defina quantidades para cada produto
   - Escolha a política de composição
4. Salve o produto

### 2. Configuração CDP (Opcional)

1. Na aba **CDP - Dimensões Personalizadas**:
   - Ative as dimensões personalizadas
   - Configure limites máximos
   - Defina preço por cm³ adicional
2. O sistema calculará automaticamente pacotes de excedente se necessário

### 3. Visualização no Carrinho

Quando um produto composto é adicionado ao carrinho, será exibido:
- 📦 **Produtos Inclusos**: Lista dos produtos filhos
- 📏 **Dimensões do Composto**: Dimensões calculadas
- ⚖️ **Peso Total**: Peso derivado
- 📋 **Pacotes de Excedente**: Se aplicável
- 🔧 **Política de Composição**: Método utilizado

## Estrutura Técnica

### Classe `SPS_Composed_Product`

```php
// Verificar se é produto composto
SPS_Composed_Product::is_composed_product($product_id);

// Obter produtos filhos
SPS_Composed_Product::get_product_children($product_id);

// Calcular dimensões derivadas
SPS_Composed_Product::get_derived_dimensions($product_id);

// Verificar pacotes de excedente
SPS_Composed_Product::has_excess_packages($cart_item);
```

### Extensões CDP

```php
// Verificar se é produto composto (CDP)
CDP_Multi_Packages::is_composed_product($product_id);

// Obter pacotes para shipping
CDP_Multi_Packages::get_composed_packages_for_shipping($cart_item);

// Verificar excedente
CDP_Multi_Packages::has_excess_packages($cart_item);
```

### Meta Fields Utilizados

- `_sps_product_type`: Tipo do produto (`composed`)
- `_sps_composed_children`: Array dos produtos filhos
- `_sps_composition_policy`: Política de composição
- `_sps_derived_dimensions`: Dimensões calculadas (cache)
- `_sps_stackable`: Configuração de empilhamento (sempre `false` para compostos)

## Políticas de Composição

### Soma de Volumes (`sum_volumes`)
- **Peso**: Soma dos pesos individuais
- **Volume**: Soma dos volumes individuais
- **Dimensões**: Calculadas a partir do volume total (cubo)
- **Uso**: Produtos que podem ser "desmontados" ou compactados

### Caixa Envolvente (`bounding_box`)
- **Peso**: Soma dos pesos individuais
- **Dimensões**: Menor caixa que engloba todos os produtos
- **Volume**: Largura × Altura × Comprimento da caixa
- **Uso**: Produtos que mantêm forma física individual

## Integração com Sistemas Existentes

### WooCommerce
- ✅ Compatível com carrinho e checkout
- ✅ Integração com cálculo de frete
- ✅ Suporte a variações (produtos filhos podem ser variações)
- ✅ Hooks padrão do WooCommerce

### CDP (Custom Dimension Products)
- ✅ Validação de volume
- ✅ Geração de pacotes de excedente
- ✅ Cálculo de preço adicional
- ✅ Interface administrativa integrada

### SPS (Stackable Product Shipping)
- ✅ Exclusão automática do empilhamento
- ✅ Integração com sistema de grupos
- ✅ Compatibilidade com múltiplos pacotes
- ✅ Processamento no shipping matcher

## Testes

Para executar os testes automatizados:

1. Acesse: `seu-site.com/wp-content/plugins/stackable-product-shipping/test-composed-products.php?run_tests=1`
2. Os testes verificarão:
   - Existência de classes
   - Tabelas do banco de dados
   - Criação de produtos compostos
   - Derivação de dimensões
   - Integração CDP
   - Regras de empilhamento
   - Funcionalidade do carrinho

## Limitações e Considerações

### Limitações Atuais
- Produtos filhos devem existir e estar publicados
- Não suporta produtos compostos aninhados (composto de compostos)
- Dimensões são recalculadas a cada salvamento

### Considerações de Performance
- Cache de dimensões derivadas
- Cálculos otimizados para políticas de composição
- Queries eficientes para produtos filhos

### Segurança
- Validação de dados de entrada
- Sanitização de campos
- Verificação de permissões administrativas

## Troubleshooting

### Problema: Dimensões não são calculadas
**Solução**: Verifique se os produtos filhos têm dimensões configuradas

### Problema: Pacotes de excedente não são gerados
**Solução**: Confirme se o CDP está ativado e configurado para o produto

### Problema: Produto ainda aparece como empilhável
**Solução**: Salve o produto novamente para atualizar as regras de empilhamento

### Problema: Erro no carrinho
**Solução**: Verifique se todos os produtos filhos ainda existem

## Suporte e Desenvolvimento

Esta funcionalidade foi desenvolvida como extensão do plugin **Stackable Product Shipping v7** com integração completa ao sistema **CDP (Custom Dimension Products)**.

Para suporte técnico ou melhorias, consulte:
- Logs do WordPress (`wp-content/debug.log`)
- Console do navegador para erros JavaScript
- Arquivo de testes para validação de funcionalidades

---

**Versão**: 1.0  
**Compatibilidade**: WordPress 5.0+, WooCommerce 4.0+  
**Última atualização**: Janeiro 2025