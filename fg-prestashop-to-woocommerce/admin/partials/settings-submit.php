				<tr>
					<th scope="row">&nbsp;</th>
					<td>
						<div class="submit_button_with_spinner">
							<?php submit_button( __('Test the database connection', 'fg-prestashop-to-woocommerce'), 'secondary', 'test_database', false ); ?>
							<span id="database_test_spinner" class="spinner"></span>
						</div>
						<div id="database_test_message" class="action_message"></div>
					</td>
				</tr>
