"use strict";

const serverApi = {
  async callUrl({
    data,
    url,
    method = 'GET'
  }) {
    debug.log(`[Request] URL: ${url}`, {
      data,
      method
    });
    let body = null;
    if (data) {
      body = typeof data === 'string' ? data : JSON.stringify(data);
    }
    const headers = new Headers();
    headers.append('Content-Type', 'application/json');
    headers.append('Cache-Control', 'no-cache');
    try {
      const response = await fetch(url, {
        method,
        headers,
        body
      });
      const isJSON = response.headers.get('Content-Type')?.includes('application/json');
      if (response.status === 200 && isJSON) {
        const responseData = await response.json();
        debug.log(`[Response] URL: ${url}`, responseData);
        return {
          data: responseData
        };
      }
      const errorMessage = `Failed calling ${url}` + `, status: ${response.status}` + `, statusText: ${response.statusText}` + `, isJSON: ${isJSON}` + `, body: ${isJSON ? response.json() : response.text()}`;
      debug.error(errorMessage);
      return {
        error: new Error(errorMessage),
        skip: response.status === 422
      };
    } catch (e) {
      debug.error('Error in callUrl', e);
      return {
        error: e
      };
    }
  }
};