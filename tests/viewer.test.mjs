import test from 'node:test';
import assert from 'node:assert/strict';
import { viewerCopy, viewerDimensions } from '../apps/web/src/lib/viewer.ts';
const config={mode:'procedural',geometry:'slab',dimensions_m:{width:.07,length:.2,depth:.02},sizes:[{id:'size-a',label:'4',dimensions_m:{width:.07,length:.2,depth:.02}},{id:'size-b',label:'Explicit',dimensions_m:{width:.1,length:.3,depth:.015}}],default_size_id:'size-a'};
test('procedural dimensions preserve explicit scene proportions and only assigned complete sizes',()=>{
 assert.deepEqual(viewerDimensions(config),config.dimensions_m);assert.deepEqual(viewerDimensions(config,'size-b'),config.sizes[1].dimensions_m);
 assert.equal(viewerDimensions(config,'unassigned'),null);
 for(const value of [null,{width:1,length:0,depth:1},{width:1,length:1,depth:Infinity},{width:101,length:1,depth:1},{width:'1',length:1,depth:1}])assert.equal(viewerDimensions({...config,sizes:[],dimensions_m:value}),null);
 assert.equal(viewerDimensions({...config,mode:'uploaded_glb'},'size-b'),null);
});
test('viewer labels and honest geometry/finish guidance are localized with configured fallback',()=>{
 for(const locale of ['fr','ar'])for(const [key,value]of Object.entries(viewerCopy(locale)))assert.ok(typeof value==='string'&&value.length>0,key);
 assert.equal(viewerCopy('fr').open,'Voir en 3D');assert.equal(viewerCopy('ar').open,'عرض ثلاثي الأبعاد');assert.deepEqual(viewerCopy('missing'),viewerCopy('fr'));
 assert.match(viewerCopy('fr').original,/ne déforment pas/);assert.match(viewerCopy('fr').indicative,/n’est pas un relevé 3D/);
});
