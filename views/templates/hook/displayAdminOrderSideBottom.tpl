{if isset($logNbz) && $logNbz != null}

  <div class="card mt-2" data-role="message-card">
      <div class="card-header">
          <div class="row">
              <div class="col-md-6">
                  <h3 class="card-header-title">
                  Niubiz Log
                  </h3>
              </div>
          </div>
      </div>

      <div class="card-body d-print-none">
          
          <img src="{$logoNiubiz|escape:'html':'UTF-8'}" style="max-height:40px">
          
          <table class="table table-bordered">
              <tr>
                  <th>Registro</th>
                  <th>Valor</th>
                </tr>
                
              {if $logNbz.id}
              <tr>
                  <td>id_log</td>
                  <td>{$logNbz.id|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
              
              {if $logNbz.id_order}
              <tr>
                  <td>id_order</td>
                  <td>{$logNbz.id_order|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
              
              {if $logNbz.id_cart}
              <tr>
                  <td>id_cart</td>
                  <td>{$logNbz.id_cart|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
              
              {if $logNbz.id_customer}
              <tr>
                  <td>id_customer</td>
                  <td>{$logNbz.id_customer|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
              
              {if $logNbz.dsc_cod_accion}
              <tr>
                  <td>Cod Action</td>
                  <td>{$logNbz.dsc_cod_accion|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
              
              {if $logNbz.transactiontoken}
              <tr>
                  <td>transactiontoken</td>
                  <td>{$logNbz.transactiontoken|escape:'html':'UTF-8'}
                  <button onclick="copyToClipboard('{$logNbz.transactiontoken|escape:'javascript':'UTF-8'}')" class="btn btn-sm btn-default"> Copiar </button></td>
              </tr>
              {/if}
              
              {if $logNbz.pan}
              <tr>
                  <td>PAN de tarjeta</td>
                  <td>{$logNbz.pan|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
              
               {if $logNbz.numorden}
              <tr>
                  <td>Numero de pedido</td>
                  <td>{$logNbz.numorden|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
              
               {if $logNbz.dsc_eci}
              <tr>
                  <td>Tipo</td>
                  <td>{$logNbz.dsc_eci|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
              
               {if $logNbz.date_add}
              <tr>
                  <td>Fecha</td>
                  <td>{$logNbz.date_add|escape:'html':'UTF-8'}</td>
              </tr>
              {/if}
          
          </table>
      </div>
  </div>

  {literal}
  <script>
  function copyToClipboard(text) {
      if (text != undefined) {
          navigator.clipboard.writeText(text);
      }
  }
  </script>
  {/literal}
{/if}
