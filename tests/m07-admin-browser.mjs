import assert from 'node:assert/strict';
import {runCustomersBrowser} from './customers-auth-browser.mjs';
for(const key of ['M07_HTTP_BASE_URL','M07_HTTP_ADMIN_EMAIL','M07_HTTP_ADMIN_PASSWORD'])assert.ok(process.env[key],key+' is required');
const checks=await runCustomersBrowser({base:process.env.M07_HTTP_BASE_URL,email:process.env.M07_HTTP_ADMIN_EMAIL,password:process.env.M07_HTTP_ADMIN_PASSWORD});
console.log('PASS: '+checks.length+' authenticated customer browser checks.');
